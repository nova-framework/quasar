<?php

namespace Quasar\Http\Controllers;

use Quasar\Platform\Http\Controller;
use Quasar\Platform\Http\Response;
use Quasar\Platform\View;

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

            $view = View::make($layout, array('content' => $response));

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

        if (preg_match('#^Quasar/Http/Controllers/(.*)$#', $classPath, $matches) === 1) {
            $view = $matches[1] .'/' .$view;

            return View::make($view, $data);
        }

        throw new BadMethodCallException('Invalid Controller namespace');
    }
}
