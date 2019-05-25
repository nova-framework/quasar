<?php

namespace Quasar\Hashing;

use Quasar\Hashing\BcryptHasher;
use Quasar\ServiceProvider;


class HashServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the Provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Register the Service Provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('hash', function()
        {
            return new BcryptHasher();
        });
    }

    /**
     * Get the Services provided by the Provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('hash');
    }

}
