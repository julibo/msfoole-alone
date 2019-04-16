<?php
/**
 * websocket应用
 */

namespace Julibo\Msfoole;

use Swoole\Table;
use Swoole\Websocket\Server;
use Swoole\WebSocket\Frame;
use Swoole\Http\Request as SwooleRequest;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Log;

class WebSocket
{
    /**
     * http请求
     * @var
     */
    private $httpRequest;

    /**
     * websocket内存表
     * @var
     */
    private $table;

    /**
     * @var
     */
    private $websocketFrame;


    // 开始时间和内存占用
    private $beginTime;
    private $beginMem;

    /**
     * @var 通道
     */
    private $chan;

    public function __construct(Table $table,  Channel $chan)
    {
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();
        $this->chan = $chan;
        $this->table = $table;
        $this->init();
    }

    /**
     * 初始化
     * @return mixed
     */
    private function init()
    {
        Log::setEnv($this->httpRequest)->info('请求开始，请求参数为 {message}', ['message' => json_encode($this->httpRequest->params)]);
    }


    /**
     * webSocket连接开启
     * @param Server $server
     * @param SwooleRequest $request
     */
    public function open(Server $server, SwooleRequest $request)
    {
        try {
            $this->httpRequest = new HttpRequest($request);
            $params = $this->httpRequest->getQuery();
            $authClass = Config::get('msfoole.websocket.login_class');
            $authAction = Config::get('msfoole.websocket.login_action');
            if(!class_exists($authClass) || !is_callable(array($authClass, $authAction))) {
                $server->disconnect($request->fd, Prompt::$common['OTHER_ERROR']['code'], Prompt::$common['OTHER_ERROR']['msg']);
            }
            $user = call_user_func_array([new $authClass, $authAction], [$params]);
            if (empty($user)) {
                $server->disconnect($request->fd, 666, '用户认证失败');
            } else {
                $token = Helper::guid();
                $user['ip'] = $this->httpRequest->getRemoteAddr();
                // 创建内存表记录
                $this->table->set($request->fd, ['token' => $token, 'counter' => 0, 'create_time' => time(), 'update_time'=>time(), 'user'=>json_encode($user)]);
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
    public function handling(Server $server, Frame $frame)
    {
        try {
            $this->websocketFrame = WebSocketFrame::getInstance($server, $frame);
            // 解析并验证请求
            $checkResult = $this->explainMessage($this->websocketFrame->getData());

            if ($checkResult === false) {
                $this->websocketFrame->disconnect($frame->fd, Prompt::$common['SIGN_EXCEPTION']['code'],  Prompt::$common['SIGN_EXCEPTION']['msg']);
            } else {
                $result = $this->runing($checkResult);
                if (Config::get('application.debug')) {
                    $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
                    $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
                    $data = ['code'=>0, 'msg'=>'', 'data'=>$result, 'requestId'=>$checkResult['requestId'], 'executionTime' =>$executionTime, 'consumeMem' => $consumeMem];
                } else {
                    $data = ['code'=>0, 'msg'=>'', 'data'=>$result, 'requestId'=>$checkResult['requestId']];
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
            if ($e->getCode() >= 1000) {
                throw $e;
            }
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
        $controller = Loader::factory($args['module'], Config::get('msfoole.websocket.namespace'));
        if(!is_callable(array($controller, $args['method']))) {
            throw new Exception(Prompt::$common['METHOD_NOT_EXIST']['msg'], Prompt::$common['METHOD_NOT_EXIST']['code']);
        } else {
//            Log::setEnv($args['requestId'], 'websocket', "{$args['module']}/{$args['method']}", $args['user']->ip ?? '');
//            Log::info('请求开始，请求参数为 {message}', ['message' => json_encode($args['arguments'])]);
            $controller->init($args['token'], $args['user'], $args['arguments']);
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
        Log::setEnv($this->httpRequest)->info('请求结束，执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        if ($executionTime > Config::get('log.slow_time')) {
            Log::setEnv($this->httpRequest)->slow('当前方法执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        }
        unset($this->chan, $this->cookie, $this->httpRequest, $this->httpResponse);

    }

    /**
     * 释放资源
     */
    public function __destruct()
    {
        $this->destruct();
    }

}