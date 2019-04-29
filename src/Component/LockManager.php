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

use Swoole\Lock;

class LockManager
{
    use Singleton;

    private $list = [];

    /**
     * 创建一个锁
     * @param string $name 锁名称
     * @param string $type 锁类型
     * @param string $filename 锁定文件 文件锁必须传入
     */
    public function add($name, $type, $filename = null): void
    {
        if (!isset($this->list[$name])) {
            $lock = new Lock($type, $filename);
            $this->list[$name] = $lock;
        }
    }

    /**
     * 获取一个锁
     * @param string $name 锁名称
     * @return Lock|null
     */
    public function get($name): ?Lock
    {
        if (isset($this->list[$name])) {
            return $this->list[$name];
        } else {
            return null;
        }
    }
}
