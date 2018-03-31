<?php

return array(

    /**
     * Debug Mode
     */
    'debug' => true, // When enabled the actual PHP errors will be shown.

    /**
     * The Website URL.
     */
    'url' => 'http://www.quasar.dev/',

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
        //'Quasar\Http\Middleware\Sample',
    ),

    /**
     * The Platform's route Middleware Groups.
     */
    'middlewareGroups' => array(
        'web' => array(
            //'Quasar\Http\Middleware\Sample',
        ),
        'api' => array(
            //'sample:60,1',
        )
    ),

    /**
     * The Platform's route Middleware.
     */
    'routeMiddleware' => array(
        //'sample' => 'Quasar\Http\Middleware\Sample',
    ),

    /**
     * The registered Class Aliases.
     */
    'aliases' => array(
        'Config'    => 'Quasar\Platform\Config',
        'Container' => 'Quasar\Platform\Container',
        'View'      => 'Quasar\Platform\View',

        // Facades
        'DB'        => 'Quasar\Platform\Support\Facades\DB',
        'Event'     => 'Quasar\Platform\Support\Facades\Event',
        'Redirect'  => 'Quasar\Platform\Support\Facades\Redirect',
        'Response'  => 'Quasar\Platform\Support\Facades\Response',
    ),
);
