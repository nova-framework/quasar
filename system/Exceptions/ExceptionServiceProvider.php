<?php

namespace System\Exceptions;

use System\Exceptions\Handler;
use System\ServiceProvider;


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
