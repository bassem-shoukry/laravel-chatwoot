<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\Data\Conversation;
use BassamShoukry\LaravelChatwoot\Enums\ConversationStatus;

it('hydrates from a Chatwoot conversation payload', function (): void {
    $conv = Conversation::from([
        'id'         => 42,
        'account_id' => 9,
        'inbox_id'   => 3,
        'status'     => 'open',
        'labels'     => ['priority', 'vip'],
        'meta'       => [
            'sender'   => ['id' => 100],
            'assignee' => ['id' => 7],
            'team'     => ['id' => 5],
        ],
    ]);

    expect($conv->id)->toBe(42)
        ->and($conv->accountId)->toBe(9)
        ->and($conv->inboxId)->toBe(3)
        ->and($conv->contactId)->toBe(100)
        ->and($conv->assigneeId)->toBe(7)
        ->and($conv->teamId)->toBe(5)
        ->and($conv->status)->toBe(ConversationStatus::Open)
        ->and($conv->labels)->toBe(['priority', 'vip']);
});

it('falls back to Open status when missing', function (): void {
    expect(Conversation::from([])->status)->toBe(ConversationStatus::Open);
});
