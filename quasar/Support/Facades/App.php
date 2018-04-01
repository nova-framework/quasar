<?php

namespace Quasar\Support\Facades;


/**
* @see \Quasar\Platform\Application
*/
class App extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'app'; }
}
