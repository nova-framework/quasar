<?php

namespace Quasar\Support\Facades;


/**
* @see \Quasar\Config
*/
class DB extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'database'; }
}
