<?php

namespace Quasar\Platform\Database;

use Quasar\Platform\Database\Manager;
use Quasar\Platform\ServiceProvider;


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
