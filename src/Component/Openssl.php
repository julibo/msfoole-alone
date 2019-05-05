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

class Openssl
{
    /**
     * @var
     */
    private $key;

    /**
     * @var string
     */
    private $method;

    /**
     * Openssl constructor.
     * @param $key
     * @param string $method
     */
    function __construct($key, $method = 'DES-EDE3')
    {
        $this->key = $key;
        $this->method = $method;
    }

    /**
     * @param string $data
     * @return string
     */
    public function encrypt(string $data)
    {
        return openssl_encrypt($data, $this->method, $this->key);
    }

    /**
     * @param string $raw
     * @return string
     */
    public function decrypt(string $raw)
    {
        return openssl_decrypt($raw, $this->method, $this->key);
    }
}
