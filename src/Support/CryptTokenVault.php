<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Support;

use BassamShoukry\LaravelChatwoot\Contracts\TokenVault;
use BassamShoukry\LaravelChatwoot\Exceptions\ConfigurationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;

final class CryptTokenVault implements TokenVault
{
    public function __construct(private readonly Encrypter $encrypter) {}

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw ConfigurationException::missing('token');
        }

        return $this->encrypter->encryptString($plaintext);
    }

    public function decrypt(string $value): string
    {
        if ($value === '') {
            throw ConfigurationException::missing('token');
        }

        try {
            return $this->encrypter->decryptString($value);
        } catch (DecryptException) {
            // Value is plaintext or otherwise unencrypted; fall back so existing
            // configurations continue to work. Production should always use encrypted tokens.
            return $value;
        }
    }
}
