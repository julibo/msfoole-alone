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

interface PoolObjectInterface
{
    //unset 的时候执行
    public function gc();

    //使用后,free的时候会执行
    public function objectRestore();

    //使用前调用,当返回true，表示该对象可用。返回false，该对象失效，需要回收
    public function beforeUse():bool ;
}
