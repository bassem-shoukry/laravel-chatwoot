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
        'secret',
        'token',
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
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $scrubbed[$key] = self::REDACTED;

                continue;
            }

            $scrubbed[$key] = is_array($value) ? self::body($value) : $value;
        }

        return $scrubbed;
    }
}
