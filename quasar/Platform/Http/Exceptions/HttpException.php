<?php

namespace Quasar\Platform\Http\Exceptions;

use Exception;
use RuntimeException;


class HttpException extends RuntimeException
{
    private $statusCode;

    public function __construct($statusCode, $message = null, Exception $previous = null, $code = 0)
    {
        $this->statusCode = $statusCode;

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
