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

namespace Julibo\Msfoole\Utility;


class Hash
{
    /**
     * 从一个明文值生产哈希
     * @param string  $value 需要生产哈希的原文
     * @param integer $cost  递归的层数 可根据机器配置调整以增加哈希的强度
     * @author : evalor <master@evalor.cn>
     * @return false|string 返回60位哈希字符串 生成失败返回false
     */
    static function makePasswordHash($value, $cost = 10)
    {
        return password_hash($value, PASSWORD_BCRYPT, [ 'cost' => $cost ]);
    }

    /**
     * 校验明文值与哈希是否匹配
     * @param string $value
     * @param string $hashValue
     * @author : evalor <master@evalor.cn>
     * @return bool
     */
    static function validatePasswordHash($value, $hashValue)
    {
        return password_verify($value, $hashValue);
    }
}