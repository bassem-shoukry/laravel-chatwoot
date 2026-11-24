<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Exceptions\ChatwootException;
use BassamShoukry\LaravelChatwoot\Exceptions\ValidationException;
use BassamShoukry\LaravelChatwoot\Http\LogScrubber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('refuses to send the token to a host other than the account URL', function (): void {
    Http::fake();

    expect(fn () => app(ChatwootManager::class)->client()->get('https://evil.example/collect'))
        ->toThrow(ChatwootException::class, 'different origin');

    Http::assertNothingSent();
});

it('still accepts an absolute URL on the account origin', function (): void {
    Http::fake(['*' => Http::response(['ok' => true])]);

    app(ChatwootManager::class)->client()->get('HTTPS://Chatwoot.test:443/api/v1/accounts/1/inboxes');

    Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://chatwoot.test')
        || str_starts_with($r->url(), 'HTTPS://Chatwoot.test'));
});

it('does not follow redirects, so api_access_token never reaches another host', function (): void {
    $client = app(ChatwootManager::class)->client();
    $options = (new ReflectionMethod($client, 'newRequest'))->invoke($client)->getOptions();

    expect($options['allow_redirects'] ?? null)->toBeFalse();
});

it('scrubs secrets from the exception context body but keeps the error text', function (): void {
    Http::fake(['*' => Http::response([
        'message'    => 'Phone number has already been taken',
        'attributes' => ['phone_number'],
        'hmac_token' => 'SECRET-HMAC',
    ], 422)]);

    try {
        app(ChatwootManager::class)->client()->post('api/v1/accounts/1/contacts', ['name' => 'x']);
        $this->fail('expected exception');
    } catch (ValidationException $e) {
        $context = $e->context();
        expect($context['status'])->toBe(422)
            ->and($context['body']['message'])->toBe('Phone number has already been taken')
            ->and($context['body']['attributes'])->toBe(['phone_number'])
            ->and($context['body']['hmac_token'])->toBe('***REDACTED***');
    }
});

it('redacts Chatwoot secret keys that exact matching missed', function (): void {
    $scrubbed = LogScrubber::body([
        'hmac_token'        => 'a',
        'pubsub_token'      => 'b',
        'page_access_token' => 'c',
        'api_access_token'  => 'd',
        'webhook_secret'    => 'e',
        'refresh_token'     => 'f',
        'provider_config'   => ['api_key' => 'g', 'phone_number_id' => '1'],
        'channel'           => ['webhook_verify_token' => 'h'],
    ]);

    expect($scrubbed)->toBe([
        'hmac_token'        => '***REDACTED***',
        'pubsub_token'      => '***REDACTED***',
        'page_access_token' => '***REDACTED***',
        'api_access_token'  => '***REDACTED***',
        'webhook_secret'    => '***REDACTED***',
        'refresh_token'     => '***REDACTED***',
        'provider_config'   => '***REDACTED***',
        'channel'           => ['webhook_verify_token' => '***REDACTED***'],
    ]);
});

it('leaves ordinary keys that merely end in _key alone', function (): void {
    expect(LogScrubber::body(['attribute_key' => 'plan', 'content' => 'hi']))
        ->toBe(['attribute_key' => 'plan', 'content' => 'hi']);
});
