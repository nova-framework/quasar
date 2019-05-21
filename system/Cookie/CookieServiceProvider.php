<?php

namespace Quasar\Cookie;

use Quasar\Support\ServiceProvider;


class CookieServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('cookie', function($app)
        {
            $config = $app['config']['session'];

            return new CookieJar($config['path'], $config['domain']);
        });
    }
}
