<?php
/**
 * websocket应用
 */

namespace Julibo\Msfoole;

use Swoole\Table;
use Swoole\Websocket\Server as Server;
use Swoole\WebSocket\Frame as Frame;
use Swoole\Http\Request as SwooleRequest;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\Component\Context\ContextManager;
use Julibo\Msfoole\Exception\Handle;
use Julibo\Msfoole\Exception\ThrowableError;

class WebSocket
{

    /**
     * @var mixed
     */
    private $beginTime;

    /**
     * @var int
     */
    private $beginMem;

    /**
     * websocket内存表
     * @var
     */
    private $table;

    /**
     * http请求
     * @var
     */
    private $request;
    
    /**
     * @var
     */
    private $websocketFrame;


    public static $muster = [];


    /**
     * WebSocket constructor.
     * @param Table $table
     */
    public function __construct(Table $table)
    {
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();
        $this->table = $table;
    }

    /**
     * webSocket连接开启
     * @param Server $server
     * @param SwooleRequest $request
     */
    public function open(Server $server, SwooleRequest $request)
    {
        try {
            $this->request = new HttpRequest($request);
            $this->request->init();
            $params = $this->request->getQuery();
            ContextManager::getInstance()->set('httpRequest', $this->request);
            Log::info('请求开始，请求参数为 {message}', ['message' => json_encode($params)]);
            ksort($params);
            $authClass = Config::get('msfoole.websocket.loginclass');
            $authAction = Config::get('msfoole.websocket.loginaction');
            if(!class_exists($authClass) || !is_callable(array($authClass, $authAction))) {
                $server->disconnect($request->fd, Prompt::$common['OTHER_ERROR']['code'], Prompt::$common['OTHER_ERROR']['msg']);
            }
            $user = call_user_func_array([new $authClass, $authAction], $params);
            if (empty($user)) {
                $server->disconnect($request->fd, Prompt::$socket['AUTH_FAILED']['code'], Prompt::$common['AUTH_FAILED']['msg']);
            } else {
                $token = Helper::guid();
                // 创建内存表记录
                $this->table->set($request->fd, ['token' => $token, 'user'=>json_encode($user)]);
                // 封装请求记录
                self::$muster[$request->fd] = $this->request;
                // 向客户端发送授权
                $server->push($request->fd, $token);
            }
        } catch (\Throwable $e) {
            $server->disconnect($request->fd,  Prompt::$common['OTHER_ERROR']['code'],  Prompt::$common['OTHER_ERROR']['msg']);
        }
    }

    /**
     * 处理websocket请求
     * @param Server $server
     * @param Frame $frame
     * @throws \Throwable
     */
    public function message(Server $server, Frame $frame)
    {
        try {
            $this->websocketFrame = WebSocketFrame::getInstance($server, $frame);
            ContextManager::getInstance()->set('httpRequest', self::$muster[$frame->fd]);
            Log::info('请求开始，请求参数为 {message}', ['message' => json_encode($frame)]);
            // 解析并验证请求
            $checkResult = $this->explainMessage($this->websocketFrame->getData());
            if ($checkResult === false) {
                $this->websocketFrame->disconnect($frame->fd, Prompt::$socket['AUTH_FAILED']['code'],  Prompt::$socket['AUTH_FAILED']['msg']);
            } else {
                $result = $this->runing($checkResult);
                if (Config::get('application.debug')) {
                    $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
                    $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
                    $data = ['code'=>0, 'data'=>$result, 'requestId'=>$checkResult['requestId'], 'executionTime' =>$executionTime, 'consumeMem' => $consumeMem];
                } else {
                    $data = ['code'=>0, 'data'=>$result, 'requestId'=>$checkResult['requestId']];
                }
                $this->websocketFrame->sendToClient($frame->fd, $data);
            }
        } catch (\Throwable $e) {
            $req = json_decode($frame->data, true);
            if (Config::get('application.debug')) {
                $data = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'data'=>[], 'requestId'=>$req['requestId'], 'extra'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]];
            } else {
                $data = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'data'=>[], 'requestId'=>$req['requestId']];
            }
            $this->websocketFrame->sendToClient($frame->fd, $data);
            // 抛出异常进行日志记录
            $this->abnormalLog($e);
        }
    }

    /**
     * 异常日志记录
     * @param \Throwable $e
     * @throws \ReflectionException
     */
    private function abnormalLog(\Throwable $e)
    {
        if (!$e instanceof \Exception) {
            $e = new ThrowableError($e);
        }
        $handler = new Handle;
        $handler->report($e, false);
        if (Config::get('application.debug')) {
            $handler->renderForConsole($e);
        }
    }

    /**
     * 解析并验证请求
     * @param array $data
     * @return array|bool
     */
    private function explainMessage(array $data)
    {
        $user = $this->table->get($this->websocketFrame->getFd());
        if (empty($user) || empty($data['data']) || empty($data['token']) || empty($data['timestamp']) || empty($data['sign'])  || empty($data['requestId'])) {
            return false;
        }
        if ($user['token'] != $data['token'] || $data['timestamp'] + 600 < time() ||  $data['timestamp'] - 600 > time()) {
            return false;
        }
        $vi = substr($data['token'], -16);
        if (Config::get('msfoole.websocket.sign') == null) {
            $pass = base64_encode(openssl_encrypt(json_encode($data['data']),"AES-128-CBC", Config::get('msfoole.websocket.key'),OPENSSL_RAW_DATA, $vi));
            if ($pass != $data['sign']) {
                return false;
            }
        } else {
            if (Config::get('msfoole.websocket.sign') != $data['sign']) {
                return false;
            }
        }
        if (empty($data['data']['timestamp']) || $data['data']['timestamp'] != $data['timestamp']) {
            return false;
        }
        return [
            'module' => $data['data']['module'] ?? Config::get('application.default.controller'),
            'method' => $data['data']['method'] ?? Config::get('application.default.action'),
            'arguments' => $data['data']['arguments'] ?? [],
            'requestId' =>  $data['requestId'],
            'user' => json_decode($user['user']),
            'token' => $data['token']
        ];
    }

    /**
     * 业务逻辑运行
     * @param array $args
     * @return mixed
     * @throws Exception
     */
    private function runing(array $args)
    {
        $controller = Loader::instance($args['module'], Config::get('msfoole.websocket.namespace'), $args['token'], $args['user'], $args['arguments']);
        if(!is_callable(array($controller, $args['method']))) {
            throw new Exception(Prompt::$common['METHOD_NOT_EXIST']['msg'], Prompt::$common['METHOD_NOT_EXIST']['code']);
        } else {
            $result = call_user_func([$controller, $args['method']]);
            return $result;
        }
    }

    /**
     * 释放资源
     * @return mixed
     */
    private function destruct()
    {
        $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
        $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
        Log::info('请求结束，执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        if ($executionTime > Config::get('log.slow_time')) {
            Log::slow('当前方法执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        }
    }

    /**
     * 释放资源
     */
    public function __destruct()
    {
        $this->destruct();
    }

}