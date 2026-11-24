<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Contracts\AccountResolver;
use BassamShoukry\LaravelChatwoot\Exceptions\AccountNotFoundException;
use BassamShoukry\LaravelChatwoot\Exceptions\ConfigurationException;
use BassamShoukry\LaravelChatwoot\Support\CryptTokenVault;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Http;

function chatwootAccount(string $url): void
{
    config()->set('chatwoot.accounts.target', ['url' => $url, 'token' => 'x', 'account_id' => 1]);
}

it('blocks loopback, unspecified and link-local hosts in every spelling', function (string $url): void {
    config()->set('chatwoot.allow_local_urls', false);
    chatwootAccount($url);

    expect(fn () => app(AccountResolver::class)->resolve('target'))
        ->toThrow(ConfigurationException::class);
})->with([
    'bracketed ipv6 loopback' => 'http://[::1]:3000',
    'other 127/8 address'     => 'http://127.0.0.2',
    'short ipv4 form'         => 'http://127.1',
    'decimal ipv4 form'       => 'http://2130706433',
    'hex ipv4 form'           => 'http://0x7f000001',
    'trailing-dot localhost'  => 'http://localhost.',
    'cloud metadata'          => 'http://169.254.169.254',
    'ipv4-mapped ipv6'        => 'http://[::ffff:127.0.0.1]',
    'unspecified ipv6'        => 'http://[::]',
    'ipv6 link-local'         => 'http://[fe80::1]',
    'zero'                    => 'http://0',
]);

it('still allows private-network (RFC 1918) self-hosted Chatwoot', function (string $url): void {
    config()->set('chatwoot.allow_local_urls', false);
    chatwootAccount($url);

    expect(app(AccountResolver::class)->resolve('target')->url)->toBe($url);
})->with([
    'http://10.0.0.5',
    'http://192.168.1.20:3000',
    'https://172.16.4.4',
    'https://chatwoot.internal.example',
]);

it('validates scheme and host even when allow_local_urls is true', function (string $url): void {
    config()->set('chatwoot.allow_local_urls', true);
    chatwootAccount($url);

    expect(fn () => app(AccountResolver::class)->resolve('target'))
        ->toThrow(ConfigurationException::class);
})->with([
    'file:///etc/passwd',
    'ftp://chatwoot.example',
    'not a url',
]);

it('picks up account config changed at runtime (tenant switch, token rotation)', function (): void {
    $resolver = app(AccountResolver::class);
    expect($resolver->resolve('default')->token)->toBe('test-token');

    config()->set('chatwoot.accounts.default.token', 'rotated-token');

    expect($resolver->resolve('default')->token)->toBe('rotated-token');
});

it('sends the rotated token from the manager after a runtime config change', function (): void {
    Http::fake(['*' => Http::response(['payload' => []])]);
    $manager = app(ChatwootManager::class);

    $manager->client()->get('api/v1/accounts/1/inboxes');
    config()->set('chatwoot.accounts.default.token', 'rotated-token');
    $manager->client()->get('api/v1/accounts/1/inboxes');

    $tokens = Http::recorded()->map(fn (array $pair): string => $pair[0]->header('api_access_token')[0])->all();
    expect($tokens)->toBe(['test-token', 'rotated-token']);
});

it('does not resolve a dotted account name as a nested config path', function (): void {
    config()->set('chatwoot.accounts.default.nested', ['url' => 'https://x.test', 'token' => 't', 'account_id' => 1]);

    expect(fn () => app(AccountResolver::class)->resolve('default.nested'))
        ->toThrow(AccountNotFoundException::class);
});

it('fails loudly on a token encrypted with a different APP_KEY', function (): void {
    $other = new Encrypter(random_bytes(32), 'AES-256-CBC');
    $mine = new Encrypter(random_bytes(32), 'AES-256-CBC');
    $ciphertext = $other->encryptString('real-token');

    expect(fn () => (new CryptTokenVault($mine))->decrypt($ciphertext))
        ->toThrow(ConfigurationException::class, 'APP_KEY');
});

it('still passes plaintext tokens through unchanged', function (): void {
    $vault = new CryptTokenVault(new Encrypter(random_bytes(32), 'AES-256-CBC'));

    expect($vault->decrypt('plain-user-token'))->toBe('plain-user-token');
});
