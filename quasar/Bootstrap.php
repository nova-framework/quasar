<?php

use Quasar\Platform\Http\Request;


$router->middleware('sample', function (Request $request, Closure $next)
{
    dump($request);

    return $next($request);
});
