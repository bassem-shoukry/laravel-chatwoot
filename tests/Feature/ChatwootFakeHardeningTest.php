<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Testing\ChatwootFake;
use Illuminate\Support\Facades\Http;

it('works without any Chatwoot credentials configured', function (): void {
    config()->set('chatwoot.accounts.default', ['url' => null, 'token' => null, 'account_id' => null]);

    $fake = ChatwootFake::swap();
    $fake->stub('POST', 'api/v1/accounts/1/conversations/9/messages', ['id' => 7, 'content' => 'hello']);

    $message = app(ChatwootManager::class)->messages()->send(9, 'hello');

    expect($message->id)->toBe(7)
        ->and($fake->calls)->toHaveCount(1);
});

it('can leave unrelated HTTP calls alone when asked', function (): void {
    ChatwootFake::swap(preventStrayRequests: false);
    Http::fake(['other.example/*' => Http::response(['ok' => true])]);

    expect(Http::get('https://other.example/ping')->json('ok'))->toBeTrue();
});

it('keeps blocking stray requests by default', function (): void {
    ChatwootFake::swap();

    expect(fn () => Http::get('https://unfaked.example/ping'))->toThrow(RuntimeException::class);
});

it('shares clients registered on an account() clone with the root manager', function (): void {
    config()->set('chatwoot.accounts.eu', ['url' => 'https://eu.chatwoot.test', 'token' => 't', 'account_id' => 2]);
    $manager = app(ChatwootManager::class);
    $fake = new ChatwootFake(accountId: 2);

    $manager->account('eu')->setClient('eu', $fake);

    expect($manager->account('eu')->client())->toBe($fake);
});
