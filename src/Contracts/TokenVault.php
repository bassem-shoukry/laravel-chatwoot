<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Contracts;

interface TokenVault
{
    public function encrypt(string $plaintext): string;

    public function decrypt(string $value): string;
}
