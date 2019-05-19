<?php

namespace Server\Http\Middleware;

use Quasar\Http\FileResponse;
use Quasar\Http\Request;
use Quasar\Http\Response;

use Closure;


class DispatchAssetFiles
{

    public function handle(Request $request, Closure $next)
    {
        if (! is_null($response = $this->dispatch($request))) {
            return $response;
        }

        return $next($request);
    }

    protected function dispatch(Request $request)
    {
        if (! in_array($request->method(), array('GET', 'HEAD'))) {
            return;
        }

        $path = $request->path();

        if ($path === 'favicon.ico') {
            // No matching is needed for this file path.
        }

        // Check if the Request instance asks for an asset file.
        else if (preg_match('#^assets/(.*)$#', $path, $matches) !== 1) {
            return;
        }

        $path = str_replace('/', DS, $matches[1]);

        return $this->createFileResponse($path);
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
