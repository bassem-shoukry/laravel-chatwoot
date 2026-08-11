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

it('sends a template with processed params, category and content', function (): void {
    Http::fake([
        '*/conversations/42/messages' => Http::response(['id' => 700], 200),
    ]);

    app(ChatwootManager::class)->messages()->sendTemplate(
        conversationId: 42,
        name: 'order_update',
        language: 'en',
        processedParams: ['1' => 'Bassem'],
        category: 'UTILITY',
        content: 'Hi Bassem, your order is ready.',
    );

    Http::assertSent(function ($request): bool {
        $raw = $request->body();

        return $request['content'] === 'Hi Bassem, your order is ready.'
            && $request['template_params']['name'] === 'order_update'
            && $request['template_params']['category'] === 'UTILITY'
            && $request['template_params']['language'] === 'en'
            // processed_params must be a JSON object, never an array — some
            // providers reject `[]` with "must be of type hash".
            && str_contains($raw, '"processed_params":{"1":"Bassem"}');
    });
});

it('sends an empty processed_params object, not an array, when no params are given', function (): void {
    Http::fake([
        '*' => Http::response(['id' => 701], 200),
    ]);

    app(ChatwootManager::class)->messages()->sendTemplate(
        conversationId: 42,
        name: 'no_params_template',
        language: 'en',
    );

    Http::assertSent(function ($request): bool {
        $raw = $request->body();

        return str_contains($raw, '"processed_params":{}')
            && ! str_contains($raw, '"processed_params":[]');
    });
});

it('omits components from the payload when none are given, and includes them when present', function (): void {
    Http::fake([
        '*' => Http::response(['id' => 702], 200),
    ]);

    app(ChatwootManager::class)->messages()->sendTemplate(42, 'order_update', 'en');

    Http::assertSent(fn ($request): bool => ! array_key_exists('components', $request['template_params']));

    app(ChatwootManager::class)->messages()->sendTemplate(
        conversationId: 42,
        name: 'order_update',
        language: 'en',
        components: [['type' => 'button', 'sub_type' => 'url']],
    );

    Http::assertSent(fn ($request): bool => ($request['template_params']['components'][0]['type'] ?? null) === 'button');
});
