<?php

use Quasar\Platform\Http\Request;

//--------------------------------------------------------------------------
// WEB Routes
//--------------------------------------------------------------------------

$router->post('apps/{appKey}/events', 'Quasar\Http\Controllers\Events@send');

$router->group(array('middleware' => 'web', 'namespace' => 'Quasar\Http\Controllers'), function ($router)
{
    $router->get('sample/{slug?}', array('uses' => 'Sample@index', 'where' => array(
        'slug' => '(.*)',
    )));

    $router->get('test', function (Request $request)
    {
        echo '<pre>' .var_export($request, true) .'</pre>';
    });
});
