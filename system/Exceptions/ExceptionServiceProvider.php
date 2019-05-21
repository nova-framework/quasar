<?php

namespace Quasar\Exceptions;

use Quasar\Exceptions\Handler;
use Quasar\Support\ServiceProvider;


class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('exception', function ($app)
        {
            return new Handler($app);
        });
    }
}
