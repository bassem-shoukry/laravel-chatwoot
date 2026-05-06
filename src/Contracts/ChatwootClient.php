<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Contracts;

interface ChatwootClient
{
    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function patch(string $path, array $payload = []): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = []): array;

    /**
     * @return array<string, mixed>
     */
    public function delete(string $path): array;

    public function baseUrl(): string;

    public function accountId(): int;
}
