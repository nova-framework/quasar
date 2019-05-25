<?php

namespace Quasar\Support\Facades;


/**
* @see \Quasar\Hashing\BcryptHasher
*/
class Hash extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'hash'; }
}
