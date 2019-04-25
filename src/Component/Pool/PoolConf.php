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

use Julibo\Msfoole\Component\Pool\Exception\PoolObjectNumError;

class PoolConf
{
    /**
     * @var float|int
     */
    protected $intervalCheckTime = 30*1000;

    /**
     * @var int
     */
    protected $maxIdleTime = 15;

    /**
     * @var int
     */
    protected $maxObjectNum = 20;

    /**
     * @var int
     */
    protected $minObjectNum = 5;

    /**
     * @var float
     */
    protected $getObjectTimeout = 3.0;

    /**
     * @var array
     */
    protected $extraConf = [];

    /**
     * @return float|int
     */
    public function getIntervalCheckTime()
    {
        return $this->intervalCheckTime;
    }

    /**
     * @param $intervalCheckTime
     * @return PoolConf
     */
    public function setIntervalCheckTime($intervalCheckTime): PoolConf
    {
        $this->intervalCheckTime = $intervalCheckTime;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxIdleTime(): int
    {
        return $this->maxIdleTime;
    }

    /**
     * @param int $maxIdleTime
     * @return PoolConf
     */
    public function setMaxIdleTime(int $maxIdleTime): PoolConf
    {
        $this->maxIdleTime = $maxIdleTime;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxObjectNum(): int
    {
        return $this->maxObjectNum;
    }

    public function setMaxObjectNum(int $maxObjectNum): PoolConf
    {
        if($this->minObjectNum >= $maxObjectNum){
            throw new PoolObjectNumError('min num is bigger than max');
        }
        $this->maxObjectNum = $maxObjectNum;
        return $this;
    }

    /**
     * @return float
     */
    public function getGetObjectTimeout(): float
    {
        return $this->getObjectTimeout;
    }

    /**
     * @param float $getObjectTimeout
     * @return PoolConf
     */
    public function setGetObjectTimeout(float $getObjectTimeout): PoolConf
    {
        $this->getObjectTimeout = $getObjectTimeout;
        return $this;
    }

    /**
     * @return array
     */
    public function getExtraConf(): array
    {
        return $this->extraConf;
    }

    /**
     * @param array $extraConf
     * @return PoolConf
     */
    public function setExtraConf(array $extraConf): PoolConf
    {
        $this->extraConf = $extraConf;
        return $this;
    }

    /**
     * @return int
     */
    public function getMinObjectNum(): int
    {
        return $this->minObjectNum;
    }

    public function setMinObjectNum(int $minObjectNum): PoolConf
    {
        if($minObjectNum >= $this->maxObjectNum){
            throw new PoolObjectNumError('min num is bigger than max');
        }
        $this->minObjectNum = $minObjectNum;
        return $this;
    }

}