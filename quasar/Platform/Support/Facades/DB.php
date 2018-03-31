<?php

namespace Quasar\Platform\Support\Facades;


/**
* @see \Quasar\Platform\Config
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
