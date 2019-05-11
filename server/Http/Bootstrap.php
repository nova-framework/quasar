<?php

use System\Http\Request;

//--------------------------------------------------------------------------
// WEB Routes
//--------------------------------------------------------------------------

$router->post('apps/{appKey}/events', 'Server\Http\Controllers\Events@send');

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
