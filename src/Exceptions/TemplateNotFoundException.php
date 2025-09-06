<?php

namespace BassamShoukry\LaravelChatwoot\Exceptions;

use Exception;

class TemplateNotFoundException extends Exception
{
    public function __construct(string $message = 'Chatwoot template not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
