<?php

use Quasar\Platform\Http\Request;
use Quasar\Platform\View;


//--------------------------------------------------------------------------
// WEB Routes
//--------------------------------------------------------------------------

$router->post('apps/{appId}/events', 'Quasar\Http\Controllers\Events@send');

$router->get('sample/{slug}', array(
    'uses'  => 'Quasar\Http\Controllers\Sample@index',
    'where' => array(
        'slug' => '(.*)',
    ),
));
