<?php

namespace Quasar\Platform\Session;

use Quasar\Platform\Session\Store;
use Quasar\Platform\ServiceProvider;


class SessionServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(array(Store::class, 'session'), function ($app)
        {
            return new Store($app);
        });
    }
}
