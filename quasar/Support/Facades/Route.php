<?php

namespace Quasar\Support\Facades;


/**
* @see \Quasar\Platform\Http\Router
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
