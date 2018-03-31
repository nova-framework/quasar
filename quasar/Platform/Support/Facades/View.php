<?php

namespace Quasar\Platform\Support\Facades;


/**
* @see \Quasar\Platform\View\View
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
