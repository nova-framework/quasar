<?php

namespace Server\Http\Middleware;

use Quasar\Http\Request;
use Quasar\Session\Store as Session;

use Closure;


class StartSession
{
    /**
     * @var \Quasar\Session\Store
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
