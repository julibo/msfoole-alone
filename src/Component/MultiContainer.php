<?php
/**
 * Created by PhpStorm.
 * User: carson
 * Date: 2019/4/24
 * Time: 10:43 AM
 */

namespace Julibo\Msfoole\Component;


class MultiContainer
{
    private $container = [];
    private $allowKeys = null;

    function __construct(array $allowKeys = null)
    {
        $this->allowKeys = $allowKeys;
    }

    function add($key,$item)
    {
        if(is_array($this->allowKeys) && !in_array($key,$this->allowKeys)){
            return false;
        }
        $this->container[$key][] = $item;
        return $this;
    }

    function set($key,$item)
    {
        if(is_array($this->allowKeys) && !in_array($key,$this->allowKeys)){
            return false;
        }
        $this->container[$key] = [$item];
        return $this;
    }

    function delete($key)
    {
        if(isset($this->container[$key])){
            unset($this->container[$key]);
        }
        return $this;
    }

    function get($key):?array
    {
        if(isset($this->container[$key])){
            return $this->container[$key];
        }else{
            return null;
        }
    }

    function all():array
    {
        return $this->container;
    }
}