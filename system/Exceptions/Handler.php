<?php

namespace Quasar\Exceptions;

use Quasar\Container\Container;
use Quasar\Exceptions\FatalThrowableError;
use Quasar\Http\Exceptions\HttpException;
use Quasar\Http\Request;
use Quasar\Http\Response;
use Quasar\Config;
use Quasar\Support\Facades\View;

use Workerman\Worker;

use Exception;
use Throwable;


class Handler
{
    /**
     * The Container instance.
     *
     * @var \Quasar\Container\Container
     */
    protected $container;

    /**
     * Whether or not we are in DEBUG mode.
     */
    protected $debug = false;


    /**
     * Create a new Exceptions Handler instance.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        //
        $this->debug = $container['config']->get('server.debug', true);
    }

    /**
     * Handle an uncaught exception from the application.
     *
     * @param  \Quasar\Http\Request
     * @param  \Exception|\Throwable  $e
     * @return void
     */
    public function handleException(Request $request, $e)
    {
        if (! $e instanceof Exception) {
            $e = new FatalThrowableError($e);
        }

        if (! $e instanceof HttpException) {
            $this->report($e);
        }

        return $this->render($request, $e);
    }

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        Worker::log($e);
    }

    /**
     * Render an exception as an HTTP response and send it.
     *
     * @param  \Quasar\Http\Request
     * @param  \Exception  $e
     * @return void
     */
    public function render(Request $request, Exception $e)
    {
        // Http Error Pages.
        if ($e instanceof HttpException) {
            $code = $e->getStatusCode();

            if (View::exists('Errors/' .$code)) {
                $view = View::make('Layouts/Default')
                    ->shares('title', 'Error ' .$code)
                    ->nest('content', 'Errors/' .$code, array('exception' => $e));

                return new Response($view->render(), 500);
            }
        }

        $type = $this->debug ? 'Debug' : 'Default';

        $view = View::make('Layouts/Default')
            ->shares('title', 'Whoops!')
            ->nest('content', 'Exceptions/' .$type, array('exception' => $e));

        return new Response($view->render(), 500);
    }
}
