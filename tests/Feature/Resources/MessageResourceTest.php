<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Enums\ContentType;
use BassamShoukry\LaravelChatwoot\Enums\MessageType;
use BassamShoukry\LaravelChatwoot\Exceptions\AuthenticationException;
use BassamShoukry\LaravelChatwoot\Exceptions\ValidationException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('sends a text message', function (): void {
    Http::fake([
        'chatwoot.test/api/v1/accounts/1/conversations/42/messages' => Http::response([
            'id'              => 555,
            'content'         => 'Hello',
            'message_type'    => 1,
            'content_type'    => 'text',
            'conversation_id' => 42,
        ], 200),
    ]);

    $message = app(ChatwootManager::class)->messages()->send(42, 'Hello');

    expect($message->id)->toBe(555)
        ->and($message->content)->toBe('Hello')
        ->and($message->type)->toBe(MessageType::Outgoing)
        ->and($message->contentType)->toBe(ContentType::Text);

    Http::assertSent(function ($request): bool {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/conversations/42/messages')
            && $request['content'] === 'Hello'
            && $request['message_type'] === 1;
    });
});

it('sends interactive buttons as input_select', function (): void {
    Http::fake([
        '*/conversations/42/messages' => Http::response(['id' => 901, 'content_type' => 'input_select'], 200),
    ]);

    $message = app(ChatwootManager::class)->messages()->sendInteractiveButtons(42, 'Pick one', [
        ['title' => 'Yes', 'value' => 'yes'],
        ['title' => 'No', 'value' => 'no'],
    ]);

    expect($message->id)->toBe(901);

    Http::assertSent(function ($request): bool {
        $items = $request['content_attributes']['items'] ?? [];

        return $request['content_type'] === 'input_select'
            && count($items) === 2
            && $items[0]['title'] === 'Yes'
            && $items[1]['value'] === 'no';
    });
});

it('passes raw content_attributes through sendRaw', function (): void {
    Http::fake([
        '*' => Http::response(['id' => 1], 200),
    ]);

    app(ChatwootManager::class)->messages()->sendRaw(42, [
        'flow_action' => 'navigate',
        'flow_id'     => 'abc',
    ], content: 'Open form');

    Http::assertSent(function ($request): bool {
        $attrs = $request['content_attributes'] ?? [];

        return ($attrs['flow_action'] ?? null) === 'navigate'
            && ($attrs['flow_id'] ?? null) === 'abc';
    });
});

it('translates 422 responses to ValidationException', function (): void {
    Http::fake([
        '*' => Http::response(['errors' => ['content is required']], 422),
    ]);

    expect(fn () => app(ChatwootManager::class)->messages()->send(42, ''))
        ->toThrow(ValidationException::class);
});

it('translates 401 responses to AuthenticationException', function (): void {
    Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);

    expect(fn () => app(ChatwootManager::class)->messages()->send(42, 'x'))
        ->toThrow(AuthenticationException::class);
});
