<?php

namespace System\Support\Facades;

use System\Http\Response as HttpResponse;


class Response
{

    public static function make($content, $status = 200, array $headers = array())
    {
        return new HttpResponse($content, $status, $headers);
    }

    public static function json($data, $status = 200, $headers = array(), $jsonOptions = 0)
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';

        return new HttpResponse(json_encode($data, $jsonOptions), $status, $headers);
    }

    public static function jsonp($callback, $data, $status = 200, array $headers = array())
    {
        $headers['Content-Type'] = 'application/javascript; charset=utf-8';

        return new HttpResponse($callback .'(' .json_encode($data) .');', $status, $headers);
    }
}
