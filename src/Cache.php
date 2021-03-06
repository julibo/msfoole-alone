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

class Cache
{

    /**
     * 缓存配置
     */
    protected $config = [];

    /**
     * 操作句柄
     */
    protected $handle;

    /**
     * 驱动
     * @var
     */
    protected $driver;

    /**
     * 对象初始化
     * @param array $config
     */
    public function init(array $config = [])
    {
        $this->config = $config;
        $this->handle = $this->connect($config);
    }

    /**
     * 连接缓存
     * @param array $options 配置数组
     * @return mixed
     */
    public function connect(array $options = [])
    {
        $type = !empty($options['driver']) ? $options['driver'] : 'redis';
        $this->driver = $type;
        return Loader::instance($type, '\\Julibo\\Msfoole\\Cache\\Driver\\', $options);
    }

    /**
     * @return mixed
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->handle, $method], $args);
    }
}
