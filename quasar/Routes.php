<?php

use Quasar\Platform\Http\Request;
use Quasar\Platform\View;


//--------------------------------------------------------------------------
// WEB Routes
//--------------------------------------------------------------------------

$router->post('apps/{appId}/events', 'Quasar\Http\Controllers\Events@send');

$router->group(array('prefix' => 'sample', 'middleware' => 'sample', 'namespace' => 'Quasar\Http\Controllers'), function ($router)
{
    $router->get('/{slug}', array(
        'uses'  => 'Sample@index',

        'where' => array(
            'slug' => '(.*)',
        ),
    ));
});
