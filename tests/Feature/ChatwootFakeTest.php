<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Testing\ChatwootFake;

it('records calls and returns stubbed responses', function (): void {
    /** @var ChatwootManager $manager */
    $manager = app(ChatwootManager::class);

    $fake = new ChatwootFake;
    $fake->stub('POST', 'api/v1/accounts/1/conversations/9/messages', [
        'id' => 7, 'content' => 'hello',
    ]);
    $fake->bindTo($manager, 'default');

    $msg = $manager->messages()->send(9, 'hello');

    expect($msg->id)->toBe(7)
        ->and($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['method'])->toBe('POST');
});
