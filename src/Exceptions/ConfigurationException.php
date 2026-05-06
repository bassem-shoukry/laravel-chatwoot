<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Exceptions;

final class ConfigurationException extends ChatwootException
{
    public static function missing(string $key): self
    {
        return new self("Required Chatwoot configuration [{$key}] is missing.");
    }
}
