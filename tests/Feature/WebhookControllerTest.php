<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\Events\MessageCreated;
use BassamShoukry\LaravelChatwoot\Events\WebhookReceived;
use BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware([])->group(function (): void {
        LaravelChatwootServiceProvider::routes(prefix: 'webhooks/chatwoot', middleware: []);
    });
});

it('rejects when signature verification is on and signature is missing', function (): void {
    config()->set('chatwoot.webhooks.verify_signature', true);
    config()->set('chatwoot.accounts.default.webhook.verify_signature', true);
    config()->set('chatwoot.accounts.default.webhook.secret', 'whsec_test');

    $response = $this->postJson('/webhooks/chatwoot', ['event' => 'message_created']);

    $response->assertStatus(401)
        ->assertJson(['ok' => false, 'error' => 'signature_mismatch']);
});

it('accepts a correctly signed payload and dispatches MessageCreated', function (): void {
    Event::fake([WebhookReceived::class, MessageCreated::class]);

    config()->set('chatwoot.webhooks.verify_signature', true);
    config()->set('chatwoot.accounts.default.webhook.verify_signature', true);
    config()->set('chatwoot.accounts.default.webhook.secret', 'whsec_test');

    $payload = [
        'event'        => 'message_created',
        'id'           => 999,
        'content'      => 'hi',
        'message_type' => 0,
        'conversation' => ['id' => 12],
        'sender'       => ['id' => 100, 'type' => 'contact'],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $sig = hash_hmac('sha256', $body, 'whsec_test');

    $response = $this->call('POST', '/webhooks/chatwoot', [], [], [], [
        'HTTP_X-Chatwoot-Signature' => $sig,
        'CONTENT_TYPE'              => 'application/json',
    ], $body);

    $response->assertOk()->assertJson(['ok' => true]);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(MessageCreated::class);
});

it('skips verification when verify_signature is false', function (): void {
    Event::fake();
    config()->set('chatwoot.accounts.default.webhook.verify_signature', false);

    $this->postJson('/webhooks/chatwoot', ['event' => 'message_created', 'id' => 1])
        ->assertOk();
});

it('returns 404 for unknown account', function (): void {
    $this->postJson('/webhooks/chatwoot/missing', ['event' => 'message_created'])
        ->assertStatus(404);
});
