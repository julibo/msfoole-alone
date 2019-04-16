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

use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;

class WebSocketFrame implements \ArrayAccess
{

    private $server;

    private $frame;

    private $data;

    private $fd;

    public function __construct(Websocket $server, Webframe $frame)
    {
        $this->server = $server;
        $this->frame = $frame;
        $this->data = json_decode($this->frame->data, true);
        $this->fd = $this->frame->fd;
    }

    public static function getInstance(Websocket $server, Webframe $frame)
    {
        return new static($server, $frame);
    }

    public function getServer()
    {
        return $this->server;
    }

    public function getFrame()
    {
        return $this->frame;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getFd()
    {
        return $this->fd;
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->server, $method], $params);
    }

    public function exist($fd)
    {
        return $this->server->exist($fd);
    }

    public function disconnect($fd, $code = 1000, $reason = "")
    {
        $this->server->disconnect($fd, $code, $reason);
    }

    public function pushToClient($data)
    {
        $this->sendToCllient($this->frame->fd, $data);
    }

    public function sendToClient($fd, $data)
    {
        if (is_scalar($data)) {
            $this->server->push($fd, $data);
        } elseif (is_array($data) || is_object($data)) {
            $this->server->push($fd, json_encode($data));
        }
    }

    public function pushToClients($data)
    {
        foreach ($this->server->connections as $fd) {
            $this->sendToClient($fd, $data);
        }
    }

    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]) ? true : false;
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

}

