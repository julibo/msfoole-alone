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

class Prompt
{

    public static $common = [ // 通用异常
        'SYSTEM_ERROR' => ['code' => 911, 'msg' => '系统错误'],
        'METHOD_NOT_EXIST' => ['code' => 998, 'msg' => '请求方法不存在'],
        'REQUEST_EXCEPTION' => ['code' => 989, 'msg' => '非法请求'],
        'SIGN_EXCEPTION' => ['code' => 988, 'msg' => '签名错误'],
        'UNLAWFUL_TOKEN' => ['code' => 987, 'msg' => '令牌无效'],
        'WAY_NOT_ALLOW' => ['code' => 986, 'msg' => '只支持POST和GET请求'],
        'CONN_TIMEOUT' => ['code' => 985, 'msg' => '连接超时'],
        'REQUEST_TIMEOUT' => ['code' => 984, 'msg' => '连接超时'],
        'FORCED_DISCONN' => ['code' => 983, 'msg' => '强制断开'],
        'SYSTEM_ERROR' => ['code' => 980, 'msg' => '系统错误'],
        'LOSS_ERROR' => ['code' => 981, 'msg' => '页面丢失'],
        'OTHER_ERROR' => ['code' => 982, 'msg' => '未知错误'],
        'OTHER_ERROR' => ['code' => 982, 'msg' => '未知错误'],
    ];

}
