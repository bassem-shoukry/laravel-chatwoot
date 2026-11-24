<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Support;

use BassamShoukry\LaravelChatwoot\Contracts\TokenVault;
use BassamShoukry\LaravelChatwoot\Exceptions\ConfigurationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;

final class CryptTokenVault implements TokenVault
{
    public function __construct(private readonly StringEncrypter $encrypter) {}

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
        } catch (DecryptException $e) {
            // A Laravel ciphertext that fails to decrypt was made with another
            // APP_KEY; sending it as the token would only surface later as a
            // confusing 401.
            if (self::looksEncrypted($value)) {
                throw new ConfigurationException(
                    'The Chatwoot token looks encrypted but cannot be decrypted with the current APP_KEY.',
                    0,
                    $e,
                );
            }

            // Plaintext token: pass it through so existing configurations keep working.
            return $value;
        }
    }

    private static function looksEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
