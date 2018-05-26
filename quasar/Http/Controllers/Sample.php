<?php

namespace Quasar\Http\Controllers;

use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Response;

use Quasar\Http\Controllers\BaseController;


class Sample extends BaseController
{
    protected $layout = 'Sample';

    public function index(Request $request, $slug = null)
    {
        $content = htmlspecialchars($slug);

        $content = '<p>' .htmlspecialchars($slug) .'</p><pre>' .htmlspecialchars(var_export($request, true)) .'</pre>';

        return $this->createView()
            ->shares('title', 'Sample')
            ->with('content', $content);
    }
}
