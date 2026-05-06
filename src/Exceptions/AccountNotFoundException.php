<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Exceptions;

final class AccountNotFoundException extends ChatwootException
{
    public static function for(string $name): self
    {
        return new self("Chatwoot account [{$name}] is not configured.");
    }
}
