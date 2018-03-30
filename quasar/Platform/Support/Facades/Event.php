<?php

namespace Quasar\Platform\Support\Facades;

use Quasar\Platform\Events\Dispatcher;


class Event
{

    public static function __callStatic($method, $parameters)
    {
        $instance = Dispatcher::getInstance();

        return call_user_func_array(array($instance, $method), $parameters);
    }
}
