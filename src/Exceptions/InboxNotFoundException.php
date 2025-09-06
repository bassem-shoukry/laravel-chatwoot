<?php

namespace BassamShoukry\LaravelChatwoot\Exceptions;

use Exception;

class InboxNotFoundException extends Exception
{
    public function __construct(string $message = 'Chatwoot inbox not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
