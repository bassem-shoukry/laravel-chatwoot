<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Resources;

use BassamShoukry\LaravelChatwoot\Contracts\ChatwootClient;

abstract class BaseResource
{
    public function __construct(protected readonly ChatwootClient $client) {}

    protected function accountPath(string $path = ''): string
    {
        $base = "api/v1/accounts/{$this->client->accountId()}";

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }

    /**
     * @param array<int|string, mixed> $items
     *
     * @return array<int, array<string, mixed>>
     */
    protected function arrayOfArrays(array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $rows[] = $item;
            }
        }

        return $rows;
    }
}
