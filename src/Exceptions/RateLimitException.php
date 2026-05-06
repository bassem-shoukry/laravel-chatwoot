<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Exceptions;

final class RateLimitException extends ChatwootException
{
    public ?int $retryAfter = null;

    public function withRetryAfter(?int $seconds): self
    {
        $this->retryAfter = $seconds;

        return $this;
    }
}
