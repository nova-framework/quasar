<?php

use Quasar\Platform\Http\Request;
use Quasar\Platform\View;


//--------------------------------------------------------------------------
// WEB Routes
//--------------------------------------------------------------------------

$router->post('apps/{appId}/events', 'Quasar\Http\Controllers\Events@send');

$router->get('sample/{slug}', array(
    'middleware' => 'sample',

    'uses' => function (Request $request, $slug = null)
    {
        return View::make('Layouts/Default')
            ->shares('title', 'Sample!')
            ->nest('content', 'Default', array('content' => htmlentities($slug)));
    },

    'where' => array(
        'slug' => '(.*)',
    ),
));
