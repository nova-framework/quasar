<?php

namespace Quasar\System;


class Controller
{

    public function callAction($method, array $parameters)
    {
        return call_user_func_array(array($this, $method), $parameters);
    }
}
