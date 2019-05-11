<?php

namespace System\Support\Facades;


/**
* @see \System\Events\Dispatcher
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
