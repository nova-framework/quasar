<?php

return array(

    /**
     * Debug Mode
     */
    'debug' => true, // When enabled the actual PHP errors will be shown.

    /**
     * The Website URL.
     */
    'url' => 'http://www.quasar.local:' .SERVER_PORT .'/',

    /**
     * Website Name.
     */
    'name' => 'Quasar Push Server',

    /**
     * The default Timezone for your website.
     * http://www.php.net/manual/en/timezones.php
     */
    'timezone' => 'Europe/London',

    /**
     * The Platform's Middleware stack.
     */
    'middleware' => array(
        'Server\Http\Middleware\DispatchAssetFiles',
    ),

    /**
     * The Platform's route Middleware Groups.
     */
    'middlewareGroups' => array(
        'web' => array(
            'Server\Http\Middleware\StartSession',
        ),
        'api' => array(
            //'sample:60,1',
        ),
    ),

    /**
     * The Platform's route Middleware.
     */
    'routeMiddleware' => array(
        //'sample' => 'Server\Http\Middleware\Sample',
    ),

    /**
     * The registered Service Providers.
     */
    'providers' => array(
        'System\Database\DatabaseServiceProvider',
        'System\Routing\RoutingServiceProvider',
        'System\Session\SessionServiceProvider',
        'System\View\ViewServiceProvider',
    ),

    'manifest' => storage_path(),

    /**
     * The registered Class Aliases.
     */
    'aliases' => array(
        'App'      => 'System\Support\Facades\App',
        'Config'   => 'System\Support\Facades\Config',
        'DB'       => 'System\Support\Facades\DB',
        'Event'    => 'System\Support\Facades\Event',
        'Redirect' => 'System\Support\Facades\Redirect',
        'Response' => 'System\Support\Facades\Response',
        'Route'    => 'System\Support\Facades\Route',
        'Session'  => 'System\Support\Facades\Session',
        'View'     => 'System\Support\Facades\View',
    ),
);
