<?php

namespace Server\Http\Controllers;

use Quasar\Http\Response;
use Quasar\Routing\Controller;
use Quasar\Support\Facades\View as ViewFactory;
use Quasar\View\View;

use BadMethodCallException;


class BaseController extends Controller
{
    /**
     * The currently requested Action.
     *
     * @var string
     */
    protected $action;

    /**
     * The currently used Layout.
     *
     * @var string
     */
    protected $layout = 'Default';


    public function callAction($method, array $parameters)
    {
        $this->action = $method;

        if (! is_null($response = $this->before())) {
            return $response;
        }

        $response = call_user_func_array(array($this, $method), $parameters);

        return $this->after($response);
    }

    protected function before()
    {
        //
    }

    protected function after($response)
    {
        if (($response instanceof View) && ! empty($this->layout)) {
            $layout = 'Layouts/' .$this->layout;

            $view = ViewFactory::make($layout, array('content' => $response));

            return new Response($view->render(), 200);
        } else if (! $response instanceof Response) {
            $response = new Response($response);
        }

        return $response;
    }

    protected function createView(array $data = array(), $view = null)
    {
        if (is_null($view)) {
            $view = ucfirst($this->action);
        }

        $classPath = str_replace('\\', '/', static::class);

        if (preg_match('#^Server/Http/Controllers/(.*)$#', $classPath, $matches) === 1) {
            $view = $matches[1] .'/' .$view;

            return ViewFactory::make($view, $data);
        }

        throw new BadMethodCallException('Invalid Controller namespace');
    }
}
