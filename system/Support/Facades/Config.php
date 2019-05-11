<?php

namespace Quasar\Support\Facades;


/**
* @see \Quasar\Config
*/
class Config extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'config'; }
}
