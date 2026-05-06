<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\Contracts\AccountResolver;
use BassamShoukry\LaravelChatwoot\Exceptions\AccountNotFoundException;
use BassamShoukry\LaravelChatwoot\Exceptions\ConfigurationException;
use Illuminate\Support\Facades\Crypt;

it('resolves a configured account', function (): void {
    $resolver = app(AccountResolver::class);
    $ctx = $resolver->resolve();

    expect($ctx->name)->toBe('default')
        ->and($ctx->url)->toBe('https://chatwoot.test')
        ->and($ctx->accountId)->toBe(1)
        ->and($ctx->token)->toBe('test-token');
});

it('decrypts encrypted tokens transparently', function (): void {
    config()->set('chatwoot.accounts.encrypted', [
        'url'        => 'https://chatwoot.test',
        'token'      => Crypt::encryptString('encrypted-token'),
        'account_id' => 7,
    ]);

    $ctx = app(AccountResolver::class)->resolve('encrypted');

    expect($ctx->token)->toBe('encrypted-token');
});

it('throws when account is missing', function (): void {
    app(AccountResolver::class)->resolve('nope');
})->throws(AccountNotFoundException::class);

it('throws when url is missing', function (): void {
    config()->set('chatwoot.accounts.broken', [
        'url'        => '',
        'token'      => 'x',
        'account_id' => 1,
    ]);

    app(AccountResolver::class)->resolve('broken');
})->throws(ConfigurationException::class);

it('throws when account_id is missing', function (): void {
    config()->set('chatwoot.accounts.broken', [
        'url'        => 'https://chatwoot.test',
        'token'      => 'x',
        'account_id' => 0,
    ]);

    app(AccountResolver::class)->resolve('broken');
})->throws(ConfigurationException::class);

it('rejects loopback urls when local urls are not allowed', function (): void {
    config()->set('chatwoot.allow_local_urls', false);
    config()->set('chatwoot.accounts.local', [
        'url'        => 'http://localhost:3000',
        'token'      => 'x',
        'account_id' => 1,
    ]);

    app(AccountResolver::class)->resolve('local');
})->throws(ConfigurationException::class);

it('rejects non-http schemes', function (): void {
    config()->set('chatwoot.allow_local_urls', false);
    config()->set('chatwoot.accounts.weird', [
        'url'        => 'file:///etc/passwd',
        'token'      => 'x',
        'account_id' => 1,
    ]);

    app(AccountResolver::class)->resolve('weird');
})->throws(ConfigurationException::class);
