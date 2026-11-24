<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Exceptions\ChatwootException;
use BassamShoukry\LaravelChatwoot\Exceptions\RateLimitException;
use BassamShoukry\LaravelChatwoot\Exceptions\ServerException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('chatwoot.api.retry_attempts', 3);
    config()->set('chatwoot.api.retry_delay', 0);
});

it('does not retry a POST on 5xx, so a message is never sent twice', function (): void {
    Http::fakeSequence()
        ->push(['error' => 'bad gateway'], 502)
        ->push(['id' => 9], 200);

    expect(fn () => app(ChatwootManager::class)->messages()->send(5, 'hi'))
        ->toThrow(ServerException::class);

    Http::assertSentCount(1);
});

it('does not retry a POST after a connection error or timeout', function (): void {
    $calls = 0;
    Http::fake(function () use (&$calls): never {
        $calls++;

        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    expect(fn () => app(ChatwootManager::class)->messages()->send(5, 'hi'))
        ->toThrow(ServerException::class);

    expect($calls)->toBe(1);
});

it('keeps the empty exception context on connection failure (consumers branch on it)', function (): void {
    Http::fake(fn () => throw new ConnectionException('down'));

    try {
        app(ChatwootManager::class)->client()->get('api/v1/accounts/1/conversations');
        $this->fail('expected exception');
    } catch (ServerException $e) {
        expect($e->context())->toBe([])
            ->and($e->getMessage())->toContain('down');
    }
});

it('still retries a POST on 429, which Chatwoot rejects before processing', function (): void {
    Http::fakeSequence()
        ->push(['error' => 'rate'], 429, ['Retry-After' => '0'])
        ->push(['id' => 9, 'content' => 'hi'], 200);

    $message = app(ChatwootManager::class)->messages()->send(5, 'hi');

    expect($message->id)->toBe(9);
    Http::assertSentCount(2);
});

it('retries a POST on 5xx when retry_non_idempotent is opted into', function (): void {
    config()->set('chatwoot.api.retry_non_idempotent', true);

    Http::fakeSequence()
        ->push([], 502)
        ->push(['id' => 9], 200);

    expect(app(ChatwootManager::class)->messages()->send(5, 'hi')->id)->toBe(9);
    Http::assertSentCount(2);
});

it('still retries GET on 5xx and connection errors', function (): void {
    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;

        return match ($calls) {
            1       => throw new ConnectionException('reset'),
            2       => Http::response([], 503),
            default => Http::response(['payload' => []], 200),
        };
    });

    expect(app(ChatwootManager::class)->client()->get('api/v1/accounts/1/conversations'))
        ->toHaveKey('payload');
    expect($calls)->toBe(3);
});

it('does not sleep on a Retry-After above max_retry_after and throws immediately', function (): void {
    config()->set('chatwoot.api.max_retry_after', 30);

    Http::fake(['*' => Http::response(['error' => 'rate'], 429, ['Retry-After' => '86400'])]);

    $started = microtime(true);

    try {
        app(ChatwootManager::class)->client()->get('api/v1/accounts/1/conversations');
        $this->fail('expected exception');
    } catch (RateLimitException $e) {
        expect($e->retryAfter)->toBe(86400);
    }

    expect(microtime(true) - $started)->toBeLessThan(2.0);
    Http::assertSentCount(1);
});

it('understands an HTTP-date Retry-After', function (): void {
    config()->set('chatwoot.api.max_retry_after', 30);
    $date = gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT';

    Http::fake(['*' => Http::response([], 429, ['Retry-After' => $date])]);

    try {
        app(ChatwootManager::class)->client()->get('api/v1/accounts/1/conversations');
        $this->fail('expected exception');
    } catch (RateLimitException $e) {
        expect($e->retryAfter)->toBeGreaterThan(3500);
    }

    Http::assertSentCount(1);
});

it('throws on a 2xx response that is not JSON instead of returning an empty array', function (): void {
    Http::fake(['*' => Http::response('<html>proxy login</html>', 200, ['Content-Type' => 'text/html'])]);

    try {
        app(ChatwootManager::class)->messages()->send(5, 'hi');
        $this->fail('expected exception');
    } catch (ChatwootException $e) {
        // Empty context keeps consumers that branch on context() === [] on
        // the package message instead of an empty HTTP body.
        expect($e->getMessage())->toContain('not valid JSON')
            ->and($e->context())->toBe([]);
    }
});

it('reuses the built client while the resolved account is unchanged', function (): void {
    $manager = app(ChatwootManager::class);

    expect($manager->client())->toBe($manager->client());
});

it('still treats an empty 2xx body as an empty array', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    expect(app(ChatwootManager::class)->client()->delete('api/v1/accounts/1/labels/3'))->toBe([]);
});
