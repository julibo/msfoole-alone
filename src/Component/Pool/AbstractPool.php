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

use Swoole\Coroutine\Channel;
use Julibo\Msfoole\Utility\Random;
use Julibo\Msfoole\Component\Pool\Exception\PoolObjectNumError;

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

}