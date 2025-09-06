<?php

namespace BassamShoukry\LaravelChatwoot\Exceptions;

use Exception;

class InvalidTokenException extends Exception
{
    public function __construct(string $message = 'Invalid Chatwoot token', int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
