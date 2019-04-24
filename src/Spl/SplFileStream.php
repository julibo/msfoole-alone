<?php
/**
 * Created by PhpStorm.
 * User: carson
 * Date: 2019/4/24
 * Time: 11:16 AM
 */

namespace Julibo\Msfoole\Spl;


class SplFileStream extends SplStream
{
    function __construct($file,$mode = 'c+')
    {
        $fp = fopen($file,$mode);
        parent::__construct($fp);
    }

    function lock($mode = LOCK_EX){
        return flock($this->getStreamResource(),$mode);
    }

    function unlock($mode = LOCK_UN){
        return flock($this->getStreamResource(),$mode);
    }
}