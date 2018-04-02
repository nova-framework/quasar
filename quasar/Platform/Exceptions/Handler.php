<?php

namespace Quasar\Platform\Exceptions;

use Quasar\Platform\Exceptions\FatalThrowableError;
use Quasar\Platform\Http\Exceptions\HttpException;
use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Response;
use Quasar\Platform\Config;
use Quasar\Platform\Container\Container;
use Quasar\Platform\View;

use Exception;
use Throwable;


class Handler
{
    /**
     * The Container instance.
     *
     * @var \Quasar\Platform\Container\Container
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

        $this->debug = $container['config']->get('platform.debug', true);
    }

    /**
     * Handle an uncaught exception from the application.
     *
     * @param  \Quasar\Platform\Http\Request
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
        $message = $e->getMessage();

        $code = $e->getCode();
        $file = $e->getFile();
        $line = $e->getLine();

        $trace = $e->getTraceAsString();

        $date = date('M d, Y G:iA');

        $message = "Exception information:\n
    Date: {$date}\n
    Message: {$message}\n
    Code: {$code}\n
    File: {$file}\n
    Line: {$line}\n
    Stack trace:\n
{$trace}\n
---------\n\n";

        //
        $path = STORAGE_PATH .'logs' .DS .'quasar.log';

        file_put_contents($path, $message, FILE_APPEND);
    }

    /**
     * Render an exception as an HTTP response and send it.
     *
     * @param  \Quasar\Platform\Http\Request
     * @param  \Exception  $e
     * @return void
     */
    public function render(Request $request, Exception $e)
    {
        $factory = $this->container['view'];

        // Http Error Pages.
        if ($e instanceof HttpException) {
            $code = $e->getStatusCode();

            if ($factory->exists('Errors/' .$code)) {
                $view = $factory->make('Layouts/Default')
                    ->shares('title', 'Error ' .$code)
                    ->nest('content', 'Errors/' .$code, array('exception' => $e));

                return new Response($view->render(), 500);
            }
        }

        $type = $this->debug ? 'Debug' : 'Default';

        $view = $factory->make('Layouts/Default')
            ->shares('title', 'Whoops!')
            ->nest('content', 'Exceptions/' .$type, array('exception' => $e));

        return new Response($view->render(), 500);
    }
}
