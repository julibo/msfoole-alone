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

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Julibo\Msfoole\Utility\Random;
use Julibo\Msfoole\Component\Pool\Exception\PoolObjectNumError;
use Julibo\Msfoole\Component\Context\ContextManager;

abstract class AbstractPool
{
    /**
     * @var Channel
     */
    private $poolChannel;
    /**
     * @var PoolConf
     */
    private $conf;

    /**
     * @var int
     */
    private $createdNum = 0;

    /**
     * @var array
     */
    private $objHash = [];

    /**
     * @var int
     */
    private $inuse = 0;

    /*
     * 如果成功创建了,请返回对应的obj
     */
    abstract protected function createObject();

    /**
     * AbstractPool constructor.
     * @param PoolConf $conf
     * @throws PoolObjectNumError
     */
    public function __construct(PoolConf $conf)
    {
        if ($conf->getMinObjectNum() >= $conf->getMaxObjectNum()) {
            $class = static::class;
            throw new PoolObjectNumError("pool max num is small than min num for {$class} error");
        }
        $this->conf = $conf;
        $this->poolChannel = new Channel($conf->getMaxObjectNum() + 1);
        if ($conf->getIntervalCheckTime() > 0) {
            swoole_timer_tick($conf->getIntervalCheckTime(), [$this, 'intervalCheck']);
        }
    }

    /**
     * @param float|null $timeout
     * @param int $beforeUseTryTimes
     * @return mixed|null
     */
    public function getObj(float $timeout = null, int $beforeUseTryTimes = 3)
    {
        if ($timeout === null) {
            $timeout = $this->conf->getGetObjectTimeout();
        }
        if ($beforeUseTryTimes <= 0) {
            return null;
        }
        // 懒惰创建模式
        $obj = null;
        if ($this->poolChannel->isEmpty()) {
            // 如果还没有达到最大连接数，则尝试进行创建
            if ($this->createdNum < $this->conf->getMaxObjectNum()) {
                $this->createdNum++;
                /*
                 * 创建对象的时候，请加try,尽量不要抛出异常
                 */
                $obj = $this->createObject();
                if (is_object($obj)) {
                    // 标记手动标记一个id   spl_hash 存在坑
                    $hash = Random::character(16);
                    $obj->__objectHash = $hash;
                    // 标记为false,才可以允许put回去队列
                    $this->objHash[$hash] = false;
                    if (!$this->putObject($obj)) {
                        $this->createdNum--;
                        unset($this->objHash[$hash]);
                    }
                }else{
                    $this->createdNum--;
                }
                // 同样进入调度等待,理论上此处可以马上pop出来
                $obj = $this->poolChannel->pop($timeout);
            } else {
                $obj = $this->poolChannel->pop($timeout);
            }
        } else {
            $obj = $this->poolChannel->pop($timeout);
        }
        // 对对象进行标记处理
        if (is_object($obj)) {
            // 上一步已经put object了，put object中设置了__objectHash
            $key = $obj->__objectHash;
            // 标记这个对象已经出队列了
            $this->objHash[$key] = false;
            if ($obj instanceof PoolObjectInterface) {
                // 请加try,尽量不要抛出异常
                $status = $obj->beforeUse();
                if ($status === false) {
                    $this->unsetObj($obj);
                    // 重新进入对象获取
                    return $this->getObj($timeout, $beforeUseTryTimes - 1);
                }
            }
            $this->inuse++;
            return $obj;
        } else {
            return null;
        }
    }

    /**
     * 间隔检查
     */
    protected function intervalCheck()
    {
        $this->gcObject($this->conf->getMaxIdleTime());
        $this->keepMin();
    }

    /*
     * 超过$idleTime未出队使用的，将会被回收。
     */
    public function gcObject(int $idleTime)
    {
        $list = [];
        while (true) {
            if (!$this->poolChannel->isEmpty()) {
                $obj = $this->poolChannel->pop(0.001);
                if (is_object($obj)) {
                    if (time() - $obj->last_recycle_time > $idleTime) {
                        $this->unsetObj($obj);
                    } else {
                        $this->objHash[$obj->__objectHash] = false;
                        array_push($list, $obj);
                    }
                }
            } else {
                break;
            }
        }
        foreach ($list as $item) {
            $this->putObject($item);
        }
    }

    /*
     * 彻底释放一个对象
     */
    public function unsetObj($obj): bool
    {
        if (is_object($obj)) {
            if (!isset($obj->__objectHash)) {
                return false;
            }
            $key = $obj->__objectHash;
            if (isset($this->objHash[$key])) {
                unset($this->objHash[$key]);
                $this->createdNum--;
                if ($obj instanceof PoolObjectInterface) {
                    $obj->objectRestore();
                    $obj->gc();
                }
                unset($obj);
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /*
     * 把一个对象归还到队列中
     */
    protected function putObject($object): bool
    {
        if (is_object($object)) {
            if (!isset($object->__objectHash)) {
                return false;
            }
            $hash = $object->__objectHash;
            // 不在的时候说明为其他pool对象，不允许归还，若为true,说明已经归还，禁止重复
            if (isset($this->objHash[$hash]) && ($this->objHash[$hash] === false)) {
                $object->last_recycle_time = time();
                $this->objHash[$hash] = true;
                $this->poolChannel->push($object);
                return true;
            }
        }
        return false;
    }

    /**
     * @param int|null $num
     * @return int
     */
    public function keepMin(?int $num = null): int
    {
        if ($num == null) {
            $num = $this->conf->getMinObjectNum();
        }
        if ($this->createdNum >= $num) {
            return $this->createdNum;
        } else {
            $num = $num - $this->createdNum;
        }

        for ($i = 0; $i < $num; $i++) {
            $this->createdNum++;
            $obj = $this->createObject();
            $hash = Random::character(16);
            if (is_object($obj)) {
                //标记手动标记一个id   spl_hash 存在坑
                $obj->__objectHash = $hash;
                //标记为false,才可以允许put回去队列
                $this->objHash[$hash] = false;
                if (!$this->putObject($obj)) {
                    $this->createdNum--;
                }
            }else{
                $this->createdNum--;
            }

        }
        return $this->createdNum;
    }

    /*
     * 用以解决冷启动问题,其实是是keepMin别名
     */
    public function preLoad(?int $num = null): int
    {
        return $this->keepMin($num);
    }

    /*
     * 回收一个对象
     */
    public function recycleObj($obj): bool
    {
        if (is_object($obj)) {
            if ($obj instanceof PoolObjectInterface) {
                $obj->objectRestore();
            }
            $ret = $this->putObject($obj);
            if ($ret) {
                $this->inuse--;
            }
            return true;
        }
        return false;
    }

    /**
     * @return PoolConf
     */
    protected function getPoolConfig(): PoolConf
    {
        return $this->conf;
    }

    public function getConfig(): PoolConf
    {
        return $this->conf;
    }

    /**
     * @return array
     */
    public function status()
    {
        return [
            'created' => $this->createdNum,
            'inuse' => $this->inuse,
            'max' => $this->getPoolConfig()->getMaxObjectNum(),
            'min' => $this->getPoolConfig()->getMinObjectNum()
        ];
    }

    public static function defer($timeout = null)
    {
        $key = md5(static::class);
        $obj = ContextManager::getInstance()->get($key);
        if ($obj) {
            return $obj;
        }else{
            $pool = PoolManager::getInstance()->getPool(static::class);
            if($pool instanceof self){
                $obj = $pool->getObj($timeout);
                if ($obj) {
                    Coroutine::defer(function ()use($pool, $obj) {
                        $pool->recycleObj($obj);
                    });
                    ContextManager::getInstance()->set($key, $obj);
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
