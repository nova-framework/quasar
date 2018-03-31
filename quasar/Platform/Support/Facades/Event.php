<?php

namespace Quasar\Platform\Support\Facades;


/**
* @see \Quasar\Platform\Events\Dispatcher
*/
class Event extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'events'; }
}
