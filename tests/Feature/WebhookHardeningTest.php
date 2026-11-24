<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\Events\MessageCreated;
use BassamShoukry\LaravelChatwoot\Events\WebhookReceived;
use BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Route::middleware([])->group(function (): void {
        LaravelChatwootServiceProvider::routes(prefix: 'webhooks/chatwoot', middleware: []);
    });

    config()->set('chatwoot.webhooks.verify_signature', true);
    config()->set('chatwoot.accounts.default.webhook.verify_signature', true);
    config()->set('chatwoot.accounts.default.webhook.secret', 'whsec_test');
});

/**
 * Sign the way Chatwoot does (lib/webhooks/trigger.rb):
 * X-Chatwoot-Signature: sha256=HMAC_SHA256(secret, "{timestamp}.{body}").
 *
 * @param array<string, mixed>  $payload
 * @param array<string, string> $query
 */
function postChatwootSigned(
    object $test,
    array $payload,
    ?int $timestamp = null,
    string $secret = 'whsec_test',
    string $uri = '/webhooks/chatwoot',
): TestResponse {
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $ts = (string) ($timestamp ?? time());

    return $test->call('POST', $uri, [], [], [], [
        'HTTP_X-Chatwoot-Timestamp' => $ts,
        'HTTP_X-Chatwoot-Signature' => 'sha256=' . hash_hmac('sha256', "{$ts}.{$body}", $secret),
        'CONTENT_TYPE'              => 'application/json',
    ], $body);
}

it('accepts a webhook signed the way Chatwoot signs it (timestamp.body)', function (): void {
    Event::fake([WebhookReceived::class, MessageCreated::class]);

    postChatwootSigned($this, ['event' => 'message_created', 'id' => 5, 'content' => 'hi'])
        ->assertOk()
        ->assertJson(['ok' => true]);

    Event::assertDispatched(MessageCreated::class);
});

it('rejects a timestamp-signed webhook outside the tolerance window (replay)', function (): void {
    Event::fake([WebhookReceived::class]);

    postChatwootSigned($this, ['event' => 'message_created', 'id' => 5], timestamp: time() - 3600)
        ->assertStatus(401)
        ->assertJson(['ok' => false, 'error' => 'stale_timestamp']);

    Event::assertNotDispatched(WebhookReceived::class);
});

it('honours a custom tolerance and 0 disables the window', function (): void {
    Event::fake();
    config()->set('chatwoot.webhooks.timestamp_tolerance', 0);

    postChatwootSigned($this, ['event' => 'message_created', 'id' => 5], timestamp: time() - 3600)
        ->assertOk();
});

it('rejects a non-numeric timestamp', function (): void {
    $body = '{"event":"message_created"}';
    $this->call('POST', '/webhooks/chatwoot', [], [], [], [
        'HTTP_X-Chatwoot-Timestamp' => 'abc',
        'HTTP_X-Chatwoot-Signature' => 'sha256=' . hash_hmac('sha256', "abc.{$body}", 'whsec_test'),
        'CONTENT_TYPE'              => 'application/json',
    ], $body)->assertStatus(401);
});

it('rejects a timestamp-signed webhook when the body is tampered with', function (): void {
    $ts = (string) time();
    $signedBody = '{"event":"message_created","id":5}';
    $this->call('POST', '/webhooks/chatwoot', [], [], [], [
        'HTTP_X-Chatwoot-Timestamp' => $ts,
        'HTTP_X-Chatwoot-Signature' => 'sha256=' . hash_hmac('sha256', "{$ts}.{$signedBody}", 'whsec_test'),
        'CONTENT_TYPE'              => 'application/json',
    ], '{"event":"message_created","id":6}')->assertStatus(401);
});

it('does not merge unsigned query-string params into the dispatched payload', function (): void {
    Event::fake([WebhookReceived::class, MessageCreated::class]);

    postChatwootSigned(
        $this,
        ['event' => 'message_created', 'id' => 5],
        uri: '/webhooks/chatwoot?private=1&injected=yes',
    )->assertOk();

    Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $e): bool {
        return ! array_key_exists('private', $e->payload->data)
            && ! array_key_exists('injected', $e->payload->data);
    });
});

it('returns 400 when the body is not a JSON object', function (): void {
    config()->set('chatwoot.accounts.default.webhook.verify_signature', false);

    $this->call('POST', '/webhooks/chatwoot?event=message_created', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], 'not-json')->assertStatus(400);
});

it('does not treat a dotted account route parameter as a nested config path', function (): void {
    Event::fake([WebhookReceived::class]);
    config()->set('chatwoot.webhooks.verify_signature', false);

    $this->postJson('/webhooks/chatwoot/default.webhook', ['event' => 'message_created', 'id' => 1])
        ->assertStatus(404);

    Event::assertNotDispatched(WebhookReceived::class);
});

it('lets the default account inherit the global webhook settings in the shipped config', function (): void {
    /** @var array<string, mixed> $shipped */
    $shipped = require __DIR__ . '/../../config/chatwoot.php';

    expect($shipped['accounts']['default']['webhook']['verify_signature'] ?? null)->toBeNull()
        ->and($shipped['accounts']['default']['webhook']['secret'] ?? null)->toBeNull()
        ->and($shipped['webhooks'])->toHaveKey('timestamp_tolerance');
});
