<?php
/**
 * Created by PhpStorm.
 * User: carson
 * Date: 2019/4/24
 * Time: 4:22 PM
 */

namespace Julibo\Msfoole\Component\Pool;

use Julibo\Msfoole\Component\Singleton;
use Julibo\Msfoole\Utility\Random;

class PoolManager
{
    use Singleton;

    private $pool = [];
    private $defaultConfig;
    private $anonymousMap = [];


    function __construct()
    {
        $this->defaultConfig = new PoolConf();
    }

    function getDefaultConfig()
    {
        return $this->defaultConfig;
    }

    function register(string $className, $maxNum = 20):?PoolConf
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

    /*
     * 请在进程克隆后，也就是worker start后，每个进程中独立使用
     */
    function getPool(string $key):?AbstractPool
    {
        if(isset($this->anonymousMap[$key])){
            $key = $this->anonymousMap[$key];
        }
        if(isset($this->pool[$key])){
            $item = $this->pool[$key];
            if($item instanceof AbstractPool){
                return $item;
            }else{
                $class = $item['class'];
                if(isset($item['config'])){
                    $obj = new $class($item['config']);
                    $this->pool[$key] = $obj;
                }else{
                    $config = clone $this->defaultConfig;
                    $createCall = $item['call'];
                    $obj = new $class($config,$createCall);
                    $this->pool[$key] = $obj;
                    $this->anonymousMap[get_class($obj)] = $key;
                }
                return $this->getPool($key);
            }
        }else{
            //先尝试动态注册
            if($this->register($key)){
                return $this->getPool($key);
            }else if(class_exists($key) && $this->registerAnonymous($key)){
                return $this->getPool($key);
            }
            return null;
        }
    }
}