<?php

use Quasar\Http\Request;

//--------------------------------------------------------------------------
// WEB Routes
//--------------------------------------------------------------------------

$router->group(array('middleware' => 'api', 'namespace' => 'Server\Http\Controllers'), function ($router)
{
    $router->post('apps/{appKey}/events', array('uses' => 'Events@send', 'where' => array(
        'appKey' => '([a-zA-Z0-9]{32})'
    )));
});

$router->group(array('middleware' => 'web', 'namespace' => 'Server\Http\Controllers'), function ($router)
{
    $router->get('sample/{slug?}', array('uses' => 'Sample@index', 'where' => array(
        'slug' => '(.*)',
    )));

    $router->get('test', function (Request $request)
    {
        echo '<pre>' .var_export($request, true) .'</pre>';
    });
});
