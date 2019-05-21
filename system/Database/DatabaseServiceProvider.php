<?php

namespace Quasar\Database;

use Quasar\Database\Manager;
use Quasar\Support\ServiceProvider;


class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('database', function ($app)
        {
            return new Manager($app);
        });
    }
}
