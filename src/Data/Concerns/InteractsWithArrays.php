<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data\Concerns;

trait InteractsWithArrays
{
    /**
     * @param array<int|string, mixed> $data
     */
    protected static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : (string) $value;
    }

    /**
     * @param array<int|string, mixed> $data
     */
    protected static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    /**
     * @param array<int|string, mixed> $data
     */
    protected static function bool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : (bool) $value;
    }

    /**
     * @param array<int|string, mixed> $data
     *
     * @return array<int|string, mixed>|null
     */
    protected static function arr(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }
}
