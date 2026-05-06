<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Contracts;

interface SignatureVerifier
{
    public function verify(string $payload, ?string $signature, string $secret): bool;
}
