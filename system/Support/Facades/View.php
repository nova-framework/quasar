<?php

namespace Quasar\Support\Facades;


/**
* @see \Quasar\View\View
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
