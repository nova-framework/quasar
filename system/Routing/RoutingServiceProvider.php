<?php

namespace Quasar\Routing;

use Quasar\ServiceProvider;


class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('router', function ($app)
        {
            return new Router($app);
        });
    }
}
