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

use think\Container;
use Julibo\Msfoole\Exception\ClassNotFoundException;
use Julibo\Msfoole\Component\Di;

class Loader
{
    /**
     * 实例化
     * @param $name
     * @param string $namespace
     * @param mixed ...$args
     * @return mixed
     */
    public static function instance($name, $namespace = '', ...$args)
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);
        if (class_exists($class)) {
            return new $class(...$args);
        } else {
            throw new ClassNotFoundException('class not exists:' . $class, $class);
        }
    }

    /**
     * 容器化加载
     * @param $name
     * @param string $namespace
     * @param mixed ...$args
     * @return mixed
     */
    public static function container($name, $namespace = '', ...$args)
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);
        if (class_exists($class)) {
            $key = md5(serialize($class));
            $di = Di::getInstance();
            $obj = $di->get($key);
            if (!$obj) {
                $di->set($key, $class, ...$args);
                $obj = $di->get($key);
            }
            return $obj;
        } else {
            throw new ClassNotFoundException('class not exists:' . $class, $class);
        }
    }

}
