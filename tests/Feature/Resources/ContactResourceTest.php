<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use Illuminate\Support\Facades\Http;

it('finds a contact by source id within an inbox', function (): void {
    Http::fake([
        'chatwoot.test/api/v1/accounts/1/contacts/search*' => Http::response([
            'payload' => [
                [
                    'id'              => 100,
                    'name'            => 'Ada',
                    'contact_inboxes' => [
                        ['inbox' => ['id' => 7], 'source_id' => '20111111111'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $contact = app(ChatwootManager::class)->contacts()->findBySourceId(7, '20111111111');

    expect($contact?->id)->toBe(100);
});

it('returns null when no inbox match', function (): void {
    Http::fake([
        '*' => Http::response(['payload' => []], 200),
    ]);

    expect(app(ChatwootManager::class)->contacts()->findBySourceId(7, 'missing'))->toBeNull();
});

it('creates a contact when none exists', function (): void {
    Http::fake([
        '*contacts/search*' => Http::response(['payload' => []], 200),
        '*contacts'         => Http::response([
            'payload' => ['contact' => ['id' => 222, 'name' => 'New User']],
        ], 200),
    ]);

    $contact = app(ChatwootManager::class)->contacts()
        ->findOrCreate(inboxId: 7, sourceId: 'wa-9999', name: 'New User', phoneNumber: '+1');

    expect($contact->id)->toBe(222);
});
