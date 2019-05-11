<?php

namespace Server\Http\Middleware;

use System\Http\Request;
use System\Session\Store as Session;

use Closure;


class StartSession
{
    /**
     * @var \System\Session\Store
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
