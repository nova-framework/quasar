<?php

namespace Quasar\Http\Middleware;

use Quasar\Platform\Http\Request;
use Quasar\Platform\Session\Store as Session;

use Closure;


class StartSession
{
    /**
     * @var \Quasar\Platform\Session\Store
     */
    protected $session;


    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function handle(Request $request, Closure $next)
    {
        $this->session->start();

        return $next($request);
    }
}
