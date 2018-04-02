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

        $path = $request->path();

        if ($path == 'favicon.ico') {
            return $this->createFileResponse('favicon.ico');
        }

        // Check if the Request instance asks for an asset file.
        else if (preg_match('#^assets/(.*)$#', $path, $matches) === 1) {
            $path = str_replace('/', DS, $matches[1]);

            return $this->createFileResponse($path);
        }

        return $next($request);
    }

    protected function createFileResponse($path)
    {
        $path = BASEPATH .'assets' .DS .$path;

        if (! is_readable($path)) {
            return new Response('404 Not Found', 404);
        }

        return new FileResponse($path);
    }
}
