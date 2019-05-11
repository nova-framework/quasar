<?php

namespace System\Support\Facades;


/**
* @see \System\Http\Router
*/
class Route extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'router'; }
}
