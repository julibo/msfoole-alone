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

namespace Julibo\Msfoole\Component\Pool;

use Julibo\Msfoole\Component\Singleton;

class PoolManager
{
    use Singleton;

    private $pool = [];

    private $defaultConfig;

    function __construct()
    {
        $this->defaultConfig = new PoolConf();
    }

    function getDefaultConfig()
    {
        return $this->defaultConfig;
    }

    function register(string $className, $maxNum = 20) : ?PoolConf
    {
        try{
            $ref = new \ReflectionClass($className);
            if($ref->isSubclassOf(AbstractPool::class)){
                $conf = clone $this->defaultConfig;
                $conf->setMaxObjectNum($maxNum);
                $this->pool[$className] = [
                    'class'=>$className,
                    'config'=>$conf
                ];
                return $conf;
            }else{
                return null;
            }
        }catch (\Throwable $throwable){
            return null;
        }
    }

    /**
     * 请在进程克隆后，也就是worker start后，每个进程中独立使用
     * @param string $key
     * @return AbstractPool|null
     */
    public function getPool(string $key) : ?AbstractPool
    {
        if (isset($this->pool[$key])) {
            $item = $this->pool[$key];
            if ($item instanceof AbstractPool) {
                return $item;
            } else {
                $class = $item['class'];
                $obj = new $class($item['config']);
                $this->pool[$key] = $obj;
                return $this->getPool($key);
            }
        } else {
            if($this->register($key)){
                return $this->getPool($key);
            }
            return null;
        }
    }

}
