<?php

namespace System\Http\Exceptions;

use System\Http\Exceptions\HttpException;


class NotFoundHttpException extends HttpException
{

    public function __construct($message = null, Exception $previous = null, $code = 0)
    {
        parent::__construct(404, $message, $previous, $code);
    }
}
