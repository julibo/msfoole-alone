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

namespace Julibo\Msfoole\Component;

use Swoole\Coroutine\Channel;

class ChannelManger
{
    use Singleton;

    /**
     * @var array
     */
    private $list = [];

    /**
     * @param $name
     * @param int $size
     */
    function add($name = 'default', $size = 1024) : void
    {
        if (!isset($this->list[$name])) {
            $chan = new Channel($size);
            $this->list[$name] = $chan;
        }
    }

    /**
     * @param $name
     * @return null|Channel
     */
    function get($name) : ?Channel
    {
        if (isset($this->list[$name])) {
            return $this->list[$name];
        } else {
            return null;
        }
    }
}
