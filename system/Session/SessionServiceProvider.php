<?php

namespace System\Session;

use System\Session\Store;
use System\ServiceProvider;


class SessionServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('session', function ($app)
        {
            return new Store($app);
        });
    }
}
