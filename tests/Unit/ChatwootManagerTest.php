<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\ChatwootManager;

it('switches account context immutably', function (): void {
    config()->set('chatwoot.accounts.other', [
        'url'        => 'https://other.test',
        'token'      => 'other-token',
        'account_id' => 9,
    ]);

    $manager = app(ChatwootManager::class);
    $other = $manager->account('other');

    expect($manager->client()->accountId())->toBe(1)
        ->and($other->client()->accountId())->toBe(9)
        ->and($manager->client()->accountId())->toBe(1);
});
