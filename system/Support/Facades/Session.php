<?php

namespace Quasar\Support\Facades;


/**
* @see \Quasar\Session\Store
*/
class Session extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'session'; }
}
