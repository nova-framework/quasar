<?php

//--------------------------------------------------------------------------
// WEB Routes
//--------------------------------------------------------------------------

$router->post('apps/{appId}/events', 'Quasar\Http\Controllers\Events@send');

$router->group(array('middleware' => 'sample', 'namespace' => 'Quasar\Http\Controllers'), function ($router)
{
    $router->get('sample/{slug}', array(
        'uses'  => 'Sample@index',

        'where' => array(
            'slug' => '(.*)',
        ),
    ));
});
