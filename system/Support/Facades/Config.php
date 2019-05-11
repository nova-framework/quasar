<?php

namespace System\Support\Facades;


/**
* @see \System\Config
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
