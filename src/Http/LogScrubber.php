<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Http;

final class LogScrubber
{
    private const REDACTED = '***REDACTED***';
    private const SENSITIVE_HEADERS = [
        'api_access_token',
        'authorization',
        'cookie',
        'hmac_token',
        'set-cookie',
        'x-api-key',
    ];
    private const SENSITIVE_KEYS = [
        'access_token',
        'api_key',
        'authorization',
        'password',
        'provider_config', // channel credentials (WhatsApp api_key, tokens, ...)
        'secret',
        'token',
    ];

    /** Chatwoot uses hmac_token, pubsub_token, page_access_token, webhook_secret, ... */
    private const SENSITIVE_SUFFIXES = [
        '_api_key',
        '_password',
        '_secret',
        '_token',
    ];

    /**
     * @param array<string, array<int, string>|string> $headers
     *
     * @return array<string, array<int, string>|string>
     */
    public static function headers(array $headers): array
    {
        $scrubbed = [];
        foreach ($headers as $name => $value) {
            $scrubbed[$name] = in_array(strtolower($name), self::SENSITIVE_HEADERS, true)
                ? self::REDACTED
                : $value;
        }

        return $scrubbed;
    }

    /**
     * @param array<int|string, mixed> $payload
     *
     * @return array<int|string, mixed>
     */
    public static function body(array $payload): array
    {
        $scrubbed = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $scrubbed[$key] = self::REDACTED;

                continue;
            }

            $scrubbed[$key] = is_array($value) ? self::body($value) : $value;
        }

        return $scrubbed;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_SUFFIXES as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
