<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Support;

final readonly class AccountContext
{
    public function __construct(
        public string $name,
        public string $url,
        public string $token,
        public int $accountId,
    ) {}
}
