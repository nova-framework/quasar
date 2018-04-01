<?php

namespace Quasar\Platform\Events;

use Quasar\Platform\Events\Dispatcher;
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
        $this->app->singleton(array(Dispatcher::class, 'events'), function ($app)
        {
            return new Dispatcher($app);
        });
    }
}
