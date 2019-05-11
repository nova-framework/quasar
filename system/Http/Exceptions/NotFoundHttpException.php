<?php

namespace Quasar\Http\Exceptions;

use Quasar\Http\Exceptions\HttpException;


class NotFoundHttpException extends HttpException
{

    public function __construct($message = null, Exception $previous = null, $code = 0)
    {
        parent::__construct(404, $message, $previous, $code);
    }
}
