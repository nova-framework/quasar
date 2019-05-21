<?php

namespace Quasar\Session;

use Quasar\Session\Store;
use Quasar\Support\ServiceProvider;


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
