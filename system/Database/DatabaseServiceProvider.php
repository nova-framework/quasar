<?php

namespace Quasar\Database;

use Quasar\ServiceProvider;


class DatabaseServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the Application events.
     *
     * @return void
     */
    public function boot()
    {
        $resolver = $this->app['database'];

        Model::setConnectionResolver($resolver);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('database', function ($app)
        {
            return new DatabaseManager($app);
        });
    }
}
