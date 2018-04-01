<?php

namespace Quasar\Platform\Exceptions;

use Quasar\Platform\Exceptions\Handler;
use Quasar\Platform\ServiceProvider;


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
