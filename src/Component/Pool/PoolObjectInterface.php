<?php
/**
 * Created by PhpStorm.
 * User: carson
 * Date: 2019/4/24
 * Time: 4:21 PM
 */

namespace Julibo\Msfoole\Component\Pool;


interface PoolObjectInterface
{
    //unset 的时候执行
    function gc();
    //使用后,free的时候会执行
    function objectRestore();
    //使用前调用,当返回true，表示该对象可用。返回false，该对象失效，需要回收
    function beforeUse():bool ;
}