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

namespace Julibo\Msfoole\Interfaces;

use Swoole\Http\Request;
use Swoole\Http\Response;

interface Event
{
    public static function init();

    public static function onWorkerStart();

    public static function onRequest(Request $request,Response $response) : bool ;

    public static function afterRequest(Request $request,Response $response) : void;
}