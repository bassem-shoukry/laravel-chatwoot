<?php

namespace BassamShoukry\LaravelChatwoot\Exceptions;

use Exception;

class AccountNotFoundException extends Exception
{
    public function __construct(string $message = 'Chatwoot account not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
