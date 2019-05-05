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
     * 创建工厂对象实例
     * @param $name 类名
     * @param string $namespace 命名空间
     * @param mixed ...$args
     * @return mixed
     */
    public static function factory($name, $namespace = '', ...$args)
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);
        if (class_exists($class)) {
            return Container::getInstance()->invokeClass($class, $args);
        } else {
            throw new ClassNotFoundException('class not exists:' . $class, $class);
        }
    }

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
            $key = serialize($class);
            $di = Di::getInstance();
            $di->set($key, $class, ...$args);
            return $di->get($key);
        } else {
            throw new ClassNotFoundException('class not exists:' . $class, $class);
        }
    }


}
