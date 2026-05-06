<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Exceptions;

use Exception;

class ChatwootException extends Exception
{
    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * @param array<string, mixed> $context
     */
    public function withContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
