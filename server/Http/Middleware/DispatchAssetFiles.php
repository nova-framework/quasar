<?php

namespace Server\Http\Middleware;

use System\Http\FileResponse;
use System\Http\Request;
use System\Http\Response;

use Closure;


class DispatchAssetFiles
{

    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), array('GET', 'HEAD'))) {
            $path = $request->path();

            if ($path == 'favicon.ico') {
                $path = 'assets/favicon.ico';
            }

            // Check if the Request instance asks for an asset file.
            if (preg_match('#^assets/(.*)$#', $path, $matches) === 1) {
                $path = str_replace('/', DS, $matches[1]);

                return $this->createFileResponse($path);
            }
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
