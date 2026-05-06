<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Enums\ConversationStatus;
use Illuminate\Support\Facades\Http;

it('reuses an open conversation for a contact and inbox', function (): void {
    Http::fake([
        'chatwoot.test/api/v1/accounts/1/contacts/100/conversations' => Http::response([
            'payload' => [
                ['id' => 33, 'inbox_id' => 7, 'status' => 'open'],
            ],
        ], 200),
    ]);

    $conv = app(ChatwootManager::class)->conversations()->firstOrCreateForContact(100, 7);

    expect($conv->id)->toBe(33)
        ->and($conv->status)->toBe(ConversationStatus::Open);
});

it('creates a new conversation when none open', function (): void {
    Http::fake([
        '*contacts/100/conversations' => Http::response(['payload' => []], 200),
        '*conversations'              => Http::response(['id' => 44, 'inbox_id' => 7, 'status' => 'open'], 200),
    ]);

    $conv = app(ChatwootManager::class)->conversations()
        ->firstOrCreateForContact(100, 7, sourceId: 'wa-1');

    expect($conv->id)->toBe(44);
});
