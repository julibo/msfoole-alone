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


class Str
{
    /**
     * 检查字符串中是否包含另一字符串
     * @param string       $haystack 被检查的字符串
     * @param string|array $needles  需要包含的字符串
     * @param bool         $strict   为true 则检查时区分大小写
     * @author : evalor <master@evalor.cn>
     * @return bool
     */
    static function contains($haystack, $needles, $strict = true)
    {
        // 不区分大小写的情况下 全部转为小写
        if (!$strict) $haystack = mb_strtolower($haystack);

        // 支持以数组方式传入 needles 检查多个字符串
        foreach ((array)$needles as $needle) {
            if (!$strict) $needle = mb_strtolower($needle);
            if ($needle != '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查字符串是否以某个字符串开头
     * @param string $haystack 被检查的字符串
     * @param string $needles  需要包含的字符串
     * @param bool   $strict   为true 则检查时区分大小写
     * @author : evalor <master@evalor.cn>
     * @return bool
     */
    static function startsWith($haystack, $needles, $strict = true)
    {
        // 不区分大小写的情况下 全部转为小写
        if (!$strict) $haystack = mb_strtolower($haystack);

        // 支持以数组方式传入 needles 检查多个字符串
        foreach ((array)$needles as $needle) {
            if (!$strict) $needle = mb_strtolower($needle);
            if ($needle != '' && mb_strpos($haystack, $needle) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查字符串是否以某个字符串结尾
     * @param string $haystack 被检查的字符串
     * @param string $needles  需要包含的字符串
     * @param bool   $strict   为true 则检查时区分大小写
     * @author : evalor <master@evalor.cn>
     * @return bool
     */
    static function endsWith($haystack, $needles, $strict = true)
    {
        // 不区分大小写的情况下 全部转为小写
        if (!$strict) $haystack = mb_strtolower($haystack);

        // 支持以数组方式传入 needles 检查多个字符串
        foreach ((array)$needles as $needle) {
            if (!$strict) $needle = mb_strtolower($needle);
            if ((string)$needle === mb_substr($haystack, -mb_strlen($needle))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 驼峰转下划线
     * @param string $value     待处理字符串
     * @param string $delimiter 分隔符
     * @author : evalor <master@evalor.cn>
     * @return null|string|string[]
     */
    static function snake($value, $delimiter = '_')
    {
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', $value);
            $value = mb_strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }
        return $value;
    }

    /**
     * 下划线转驼峰 (首字母小写)
     * @param string $value 待处理字符串
     * @author : evalor <master@evalor.cn>
     * @return string
     */
    static function camel($value)
    {
        return lcfirst(static::studly($value));
    }

    /**
     * 下划线转驼峰 (首字母大写)
     * @param string $value 待处理字符串
     * @author : evalor <master@evalor.cn>
     * @return mixed
     */
    static function studly($value)
    {
        $value = ucwords(str_replace([ '-', '_' ], ' ', $value));
        return str_replace(' ', '', $value);
    }

    /**
     * 获取指定长度的随机字母数字组合的字符串
     *
     * @param  int $length
     * @return string
     */
    public static function random($length = 16)
    {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return static::substr(str_shuffle(str_repeat($pool, $length)), 0, $length);
    }

    /**
     * 字符串转小写
     *
     * @param  string $value
     * @return string
     */
    public static function lower($value)
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * 字符串转大写
     *
     * @param  string $value
     * @return string
     */
    public static function upper($value)
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * 获取字符串的长度
     *
     * @param  string $value
     * @return int
     */
    public static function length($value)
    {
        return mb_strlen($value);
    }

    /**
     * 截取字符串
     *
     * @param  string   $string
     * @param  int      $start
     * @param  int|null $length
     * @return string
     */
    public static function substr($string, $start, $length = null)
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    /**
     * 转为首字母大写的标题格式
     *
     * @param  string $value
     * @return string
     */
    public static function title($value)
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}