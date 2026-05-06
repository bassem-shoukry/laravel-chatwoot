<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Webhooks;

use BassamShoukry\LaravelChatwoot\Contracts\SignatureVerifier;

final class HmacSignatureVerifier implements SignatureVerifier
{
    public function verify(string $payload, ?string $signature, string $secret): bool
    {
        if ($signature === null || $signature === '' || $secret === '') {
            return false;
        }

        $signature = $this->normalize($signature);
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function normalize(string $signature): string
    {
        if (str_starts_with($signature, 'sha256=')) {
            return substr($signature, 7);
        }

        return $signature;
    }
}
