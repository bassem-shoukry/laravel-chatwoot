<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot;

use BassamShoukry\LaravelChatwoot\Contracts\AccountResolver;
use BassamShoukry\LaravelChatwoot\Support\AccountContext;

final readonly class BoundResolver implements AccountResolver
{
    public function __construct(
        private string $name,
        private AccountResolver $inner,
    ) {}

    public function resolve(?string $name = null): AccountContext
    {
        return $this->inner->resolve($name ?? $this->name);
    }
}
