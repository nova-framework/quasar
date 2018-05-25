<?php

namespace Quasar\Platform\Routing;

use Quasar\Platform\ServiceProvider;


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
