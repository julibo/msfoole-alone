<?php
/**
 * Created by PhpStorm.
 * User: carson
 * Date: 2019/4/24
 * Time: 4:20 PM
 */

namespace Julibo\Msfoole\Component\Pool;

use Swoole\Coroutine;
use Julibo\Msfoole\Component\Context\ContextManager;
use Julibo\Msfoole\Component\Pool\Exception\PoolEmpty;
use Julibo\Msfoole\Component\Pool\Exception\PoolException;

trait TraitInvoker
{
    public static function invoke(callable $call,float $timeout = null)
    {
        $pool = PoolManager::getInstance()->getPool(static::class);
        if($pool instanceof AbstractPool){
            $obj = $pool->getObj($timeout);
            if($obj){
                try{
                    $ret = call_user_func($call,$obj);
                    return $ret;
                }catch (\Throwable $throwable){
                    throw $throwable;
                }finally{
                    $pool->recycleObj($obj);
                }
            }else{
                throw new PoolEmpty(static::class." pool is empty");
            }
        }else{
            throw new PoolException(static::class." convert to pool error");
        }
    }

    public static function defer($timeout = null)
    {
        $key = md5(static::class);
        $obj = ContextManager::getInstance()->get($key);
        if($obj){
            return $obj;
        }else{
            $pool = PoolManager::getInstance()->getPool(static::class);
            if($pool instanceof AbstractPool){
                $obj = $pool->getObj($timeout);
                if($obj){
                    Coroutine::defer(function ()use($pool,$obj){
                        $pool->recycleObj($obj);
                    });
                    ContextManager::getInstance()->set($key,$obj);
                    return $obj;
                }else{
                    throw new PoolEmpty(static::class." pool is empty");
                }
            }else{
                throw new PoolException(static::class." convert to pool error");
            }
        }
    }
}
