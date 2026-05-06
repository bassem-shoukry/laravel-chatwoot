<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Exceptions\NotFoundException;
use BassamShoukry\LaravelChatwoot\Exceptions\RateLimitException;
use BassamShoukry\LaravelChatwoot\Exceptions\ServerException;
use Illuminate\Support\Facades\Http;

it('retries on 429 and succeeds when within attempt limit', function (): void {
    config()->set('chatwoot.api.retry_attempts', 2);
    config()->set('chatwoot.api.retry_delay', 0);

    Http::fakeSequence()
        ->push(['error' => 'rate_limited'], 429, ['Retry-After' => '0'])
        ->push(['payload' => []], 200);

    $client = app(ChatwootManager::class)->client();
    $result = $client->get('api/v1/accounts/1/conversations');

    expect($result)->toHaveKey('payload');
    Http::assertSentCount(2);
});

it('raises RateLimitException when retries are exhausted', function (): void {
    config()->set('chatwoot.api.retry_attempts', 1);

    Http::fake(['*' => Http::response(['error' => 'too many'], 429, ['Retry-After' => '0'])]);

    expect(fn () => app(ChatwootManager::class)->client()->get('api/v1/accounts/1/conversations'))
        ->toThrow(RateLimitException::class);
});

it('translates 404 to NotFoundException', function (): void {
    Http::fake(['*' => Http::response(['error' => 'not found'], 404)]);

    expect(fn () => app(ChatwootManager::class)->client()->get('api/v1/accounts/1/conversations/999'))
        ->toThrow(NotFoundException::class);
});

it('retries on 5xx and surfaces ServerException after exhaustion', function (): void {
    config()->set('chatwoot.api.retry_attempts', 1);

    Http::fake(['*' => Http::response('boom', 503)]);

    expect(fn () => app(ChatwootManager::class)->client()->get('api/v1/accounts/1/conversations'))
        ->toThrow(ServerException::class);
});
