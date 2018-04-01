<?php

namespace Quasar\Platform\Events;

use Quasar\Platform\ServiceProvider;


class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('events', function ($app)
        {
            return new Dispatcher($app);
        });
    }
}
