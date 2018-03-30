<?php

namespace Quasar\System\Exceptions;


use Quasar\System\Exceptions\HttpException;


class NotFoundHttpException extends HttpException
{

    public function __construct($message = null, Exception $previous = null, $code = 0)
    {
        parent::__construct(404, $message, $previous, $code);
    }
}
