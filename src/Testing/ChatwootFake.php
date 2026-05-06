<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Testing;

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Contracts\ChatwootClient;
use Illuminate\Support\Facades\Http;

final class ChatwootFake implements ChatwootClient
{
    /** @var array<int, array{method: string, path: string, payload: array<string, mixed>}> */
    public array $calls = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $stubs = [];

    public function __construct(
        public string $baseUrl = 'https://chatwoot.test',
        public int $accountId = 1,
    ) {}

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function accountId(): int
    {
        return $this->accountId;
    }

    /**
     * @param array<string, mixed> $response
     */
    public function stub(string $method, string $path, array $response): self
    {
        $key = strtoupper($method) . ' ' . ltrim($path, '/');
        $this->stubs[$key][] = $response;

        return $this;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->record('GET', $path, $query);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->record('POST', $path, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function patch(string $path, array $payload = []): array
    {
        return $this->record('PATCH', $path, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->record('PUT', $path, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $path): array
    {
        return $this->record('DELETE', $path, []);
    }

    public function bindTo(ChatwootManager $manager, string $accountName = 'default'): void
    {
        $manager->setClient($accountName, $this);
    }

    public static function swap(string $accountName = 'default'): self
    {
        $fake = new self;
        $manager = app(ChatwootManager::class);
        $fake->bindTo($manager, $accountName);
        Http::preventStrayRequests();

        return $fake;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function record(string $method, string $path, array $payload): array
    {
        $key = strtoupper($method) . ' ' . ltrim($path, '/');
        $this->calls[] = ['method' => $method, 'path' => $path, 'payload' => $payload];

        if (isset($this->stubs[$key]) && $this->stubs[$key] !== []) {
            $stub = array_shift($this->stubs[$key]);

            return $stub;
        }

        return [];
    }
}
