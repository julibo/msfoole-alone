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

use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cookie;

abstract class Controller
{
    /**
     * @var HttpRequest
     */
    protected $request;

    /**
     * @var
     */
    protected $params;

    /**
     * @var
     */
    protected $header;

    /**
     * @var
     */
    protected $user;

    /**
     * @var
     */
    protected $token;

    /**
     * @var 
     */
    protected $clientIP;

    /**
     * AloneController constructor.
     * @param $request
     * @throws Exception
     */
    final public function __construct($request)
    {
        $this->request = $request;
        $this->header = $this->request->getHeader();
        $this->params = $this->request->params;
        $this->clientIP = $this->request->remote_addr;
        $this->authentication();
        $this->init();
    }

    /**
     * 初始化方法
     * @return mixed
     */
    abstract protected function init();

    /**
     * 通过token获取用户信息
     * @return array
     */
    protected function getUserByToken()
    {
        $this->token =  $this->header['token'] ?? null;
        if ($this->token) {
            $this->user = Cookie::getTokenCache($this->token);
        }
        return $this->user;
    }

    /**
     * 向客户端授权
     * @param array $user
     */
    protected function setToken(array $user)
    {
        Cookie::setToken($user);
    }

    /**
     * 用户鉴权
     */
    final protected function authentication()
    {
        $execute = true;
        $allow = Config::get('application.allow.controller');
        $target = static::class;
        if (is_array($allow)) {
            if (in_array($target, $allow)) {
                $execute = false;
            }
        } else {
            if ($target == $allow) {
                $execute = false;
            }
        }
        // 需要鉴权
        if ($execute) {
            $user = $this->getUserByToken();
            if ($user) {
                $this->user = $user;
            } else {
                throw new Exception("用户认证失败", 987);
            }
        }
    }
}
