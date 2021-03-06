<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole4的高性能API服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole;


use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cookie;
use Julibo\Msfoole\Exception\Handle;
use Julibo\Msfoole\Exception\ThrowableError;
use Julibo\Msfoole\Component\Context\ContextManager;

class Application
{
    /**
     * http请求
     * @var
     */
    private $httpRequest;

    /**
     * http应答
     * @var
     */
    private $httpResponse;

    // 开始时间和内存占用
    private $beginTime;
    private $beginMem;

    /**
     * Application constructor.
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     */
    public function __construct(SwooleRequest $request, SwooleResponse $response)
    {
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();
        $this->httpResponse = new Response($response);
        $this->httpRequest = new HttpRequest($request);
        $this->init();
    }

    /**
     * 初始化
     */
    private function init()
    {
        $this->httpRequest->init();
        $this->httpRequest->explain();
        ContextManager::getInstance()->set('httpRequest', $this->httpRequest);
        ContextManager::getInstance()->set('httpResponse', $this->httpResponse);
        Log::info('请求开始，请求参数为 {message}', ['message' => json_encode($this->httpRequest->params)]);
        defer(function () {
            $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
            $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
            Log::info('请求结束，执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
            if ($executionTime > Config::get('log.slow_time')) {
                Log::slow('当前方法执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
            }
            ContextManager::getInstance()->destroy();
        });
    }

    /**
     * 检验请求有效性
     * @throws Exception
     */
    private function checkRequest()
    {
        if (!in_array($this->httpRequest->getRequestMethod(), ['POST', 'GET'])) {
            throw new Exception(Prompt::$common['WAY_NOT_ALLOW']['msg'], Prompt::$common['WAY_NOT_ALLOW']['code']);
        }
        $header = $this->httpRequest->getHeader();
        if (empty($header) || empty($header['level']) || empty($header['timestamp']) || empty($header['token']) ||
            empty($header['signer']) || $header['level'] != 2 || $header['timestamp'] > strtotime('10 minutes') * 1000 ||
            $header['timestamp'] < strtotime('-10 minutes') * 1000) {
            throw new Exception(Prompt::$common['REQUEST_EXCEPTION']['msg'], Prompt::$common['REQUEST_EXCEPTION']['code']);
        }
        $signSrc = $header['timestamp'].$header['token'];
        if (!empty($this->httpRequest->params)) {
            ksort($this->httpRequest->params);
            array_walk($this->httpRequest->params, function($value,$key) use (&$signSrc) {
                $signSrc .= $key.$value;
            });
        }
        if (md5($signSrc) != $header['signer']) {
            throw new Exception(Prompt::$common['SIGN_EXCEPTION']['msg'], Prompt::$common['SIGN_EXCEPTION']['code']);
        }
        return $this;
    }

    /**
     * 检验token有效性
     * @throws Exception
     */
    private function checkToken()
    {
        $token = $this->httpRequest->getHeader('token');
        if (empty($token)) {
            throw new Exception(Prompt::$common['UNLAWFUL_TOKEN']['msg'], Prompt::$common['UNLAWFUL_TOKEN']['code']);
        } else {
            $default = Config::get('application.default.key');
            if ($default != $token) {
                $user = Cookie::getTokenCache($token);
                if (empty($user)) {
                    throw new Exception(Prompt::$common['UNLAWFUL_TOKEN']['msg'], Prompt::$common['UNLAWFUL_TOKEN']['code']);
                }
            }
        }
        return $this;
    }

    /**
     * 处理http请求
     */
    public function handling()
    {
        try {
            ob_start();
            # step 1 验证请求合法性(白名单不验证)
            $allow = Config::get('application.allow.controller');
            $target = substr($this->httpRequest->namespace . $this->httpRequest->controller, 1);
            if (!((is_array($allow) && in_array($target, $allow)) || (is_string($allow) && $allow == $target))) {
                $this->checkToken()->checkRequest();
            }
            # step 2 调用服务
            $this->working();
            $content = ob_get_clean();
            $this->httpResponse->end($content);
        } catch (\Throwable $e) {
            if ($e->getCode() == 301 || $e->getCode() == 302) {
                $this->httpResponse->redirect($e->getMessage(), $e->getCode());
            } else {
                // 异常响应
                $this->response($e);
                // 抛出异常进行日志记录
                $this->abnormalLog($e);
            }
        }
    }

    /**
     * 运行请求
     * @throws Exception
     */
    public function working()
    {
        $controller = Loader::instance($this->httpRequest->controller, $this->httpRequest->namespace, $this->httpRequest);
        if(!is_callable(array($controller, $this->httpRequest->action))) {
            throw new Exception(Prompt::$common['METHOD_NOT_EXIST']['msg'], Prompt::$common['METHOD_NOT_EXIST']['code']);
        }
        $data = call_user_func([$controller, $this->httpRequest->action]);
        if ($data === null && ob_get_contents() != '') {
        } else {
            if (Config::get('application.debug')) {
                $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
                $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
                $result = ['code' => 0, 'data' => $data, 'identification' => $this->httpRequest->identification,
                    'executionTime' =>$executionTime, 'consumeMem' => $consumeMem ];
            } else {
                $result = ['code' => 0, 'data' => $data, 'identification' => $this->httpRequest->identification];
            }
            echo json_encode($result);
        }
    }

    /**
     * 异常响应
     * @param \Throwable $e
     */
    private function response(\Throwable $e)
    {
        if ($e->getCode() == 0) {
            $code = 911;
        } else {
            $code = $e->getCode();
        }
        if (Config::get('application.debug')) {
            $content = ['code' => $code, 'msg'=>$e->getMessage(), 'identification' => $this->httpRequest->identification,
                'extra'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]];
        } else {
            $content = ['code' => $code, 'msg'=>$e->getMessage(), 'identification' => $this->httpRequest->identification];
        }
        $this->httpResponse->end(json_encode($content));
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

}
