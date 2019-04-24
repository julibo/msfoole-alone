<?php
/**
 * Created by PhpStorm.
 * User: carson
 * Date: 2019/4/24
 * Time: 3:39 PM
 */

namespace Julibo\Msfoole\Component\Context;


interface ContextItemHandlerInterface
{
    function onContextCreate();
    function onDestroy($context);
}