<?php

namespace Server\Http\Controllers;

use System\Http\Request;
use System\Http\Response;

use Server\Http\Controllers\BaseController;


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
