<?php

namespace Quasar\Http\Middleware;

use Quasar\Platform\Http\FileResponse;
use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Response;

use Closure;


class DispatchAssetFiles
{

    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->method(), array('GET', 'HEAD'))) {
            return $next($request);
        }

        // Check if the Request instance asks for an asset file.
        else if (preg_match('#^assets/(.*)$#', $request->path(), $matches) === 1) {
            $path = BASEPATH .'assets' .DS .str_replace('/', DS, $matches[1]);

            if (! is_readable($path)) {
                return new Response('404 Not Found', 404);
            }

            return new FileResponse($path);
        }

        return $next($request);
    }
}
