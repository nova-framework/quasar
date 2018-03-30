<?php

namespace Quasar\Http\Controllers;

use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Response;

use Quasar\Http\Controllers\BaseController;


class Sample extends BaseController
{

    public function index(Request $request, $slug = null)
    {
        $content = htmlspecialchars($slug);

        return $this->createView()
            ->shares('title', 'Sample')
            ->with('content', $content);
    }
}
