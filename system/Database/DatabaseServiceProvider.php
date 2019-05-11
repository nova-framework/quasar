<?php

namespace System\Database;

use System\Database\Manager;
use System\ServiceProvider;


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
