<?php

namespace System\Support\Facades;


/**
* @see \System\View\View
*/
class View extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'view'; }
}
