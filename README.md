# Laravel Chatwoot

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bassem-shoukry/laravel-chatwoot.svg?style=flat-square)](https://packagist.org/packages/bassem-shoukry/laravel-chatwoot)
[![Total Downloads](https://img.shields.io/packagist/dt/bassem-shoukry/laravel-chatwoot.svg?style=flat-square)](https://packagist.org/packages/bassem-shoukry/laravel-chatwoot)
[![License](https://img.shields.io/packagist/l/bassem-shoukry/laravel-chatwoot.svg?style=flat-square)](LICENSE.md)

Typed Chatwoot API client and webhook receiver for Laravel.

- Strongly-typed DTOs (`Conversation`, `Message`, `Contact`, `Inbox`, …)
- Resource gateways (`Chatwoot::messages()`, `Chatwoot::conversations()`, …)
- HMAC-verified webhook controller with typed events
- Multi-account, immutable account switching
- SSRF guard, scrubbed request/response logging, automatic retry on `429`/`5xx`
- Built-in `ChatwootFake` for tests
- PHP 8.3 / 8.4 · Laravel 11 / 12 / 13

## Install

```bash
composer require bassem-shoukry/laravel-chatwoot
php artisan vendor:publish --tag=laravel-chatwoot-config
```

Set the env keys:

```env
CHATWOOT_URL=https://app.chatwoot.com
CHATWOOT_API_TOKEN=your-user-api-access-token
CHATWOOT_ACCOUNT_ID=1
CHATWOOT_VERIFY_SIGNATURE=true
CHATWOOT_HMAC_SECRET=whsec_your_webhook_signing_secret
```

The `CHATWOOT_API_TOKEN` is a Chatwoot **User Access Token** (Profile → Access
Token). Tokens stored in config are decrypted with the application key on read,
so you may store them encrypted using `Crypt::encryptString` for defence in
depth.

## Send messages

```php
use BassamShoukry\LaravelChatwoot\Facades\Chatwoot;

// Plain text
Chatwoot::messages()->send($conversationId, 'Hello there');

// Interactive buttons (mapped to Chatwoot's input_select content type)
Chatwoot::messages()->sendInteractiveButtons($conversationId, 'Pick one', [
    ['title' => 'Yes', 'value' => 'yes'],
    ['title' => 'No',  'value' => 'no'],
]);

// WhatsApp template
Chatwoot::messages()->sendTemplate(
    conversationId: $conversationId,
    name: 'order_update',
    language: 'en',
    components: [/* WhatsApp template components */],
);

// Raw passthrough — escape hatch for advanced WhatsApp payloads (Flows etc.)
Chatwoot::messages()->sendRaw($conversationId, [
    'flow_action' => 'navigate',
    'flow_id'     => 'abc123',
]);
```

## Find or create a contact, then a conversation

Useful when reacting to an inbound message on a channel keyed by a `source_id`
(e.g. WhatsApp's wa_id):

```php
$contact = Chatwoot::contacts()->findOrCreate(
    inboxId: $inboxId,
    sourceId: $waId,
    name: $name,
    phoneNumber: '+'.$waId,
);

$conv = Chatwoot::conversations()->firstOrCreateForContact(
    contactId: $contact->id,
    inboxId: $inboxId,
    sourceId: $waId,
);

Chatwoot::messages()->send($conv->id, 'Welcome 👋');
```

## Multi-account

```env
CHATWOOT_ACCOUNT=primary
```

```php
'accounts' => [
    'primary' => [
        'url'        => env('CHATWOOT_URL'),
        'token'      => env('CHATWOOT_API_TOKEN'),
        'account_id' => env('CHATWOOT_ACCOUNT_ID'),
    ],
    'eu' => [
        'url'        => env('CHATWOOT_EU_URL'),
        'token'      => env('CHATWOOT_EU_API_TOKEN'),
        'account_id' => env('CHATWOOT_EU_ACCOUNT_ID'),
    ],
],
```

```php
Chatwoot::account('eu')->messages()->send($id, 'Hallo');
```

`account()` returns an immutable, scoped manager — it never mutates the shared
singleton.

## Receive webhooks

Routes are **opt-in**. Register them where you control the URL prefix and
middleware:

```php
// routes/api.php
use BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider;

LaravelChatwootServiceProvider::routes(
    prefix: 'api/webhooks/chatwoot',
    middleware: ['api'],
);
```

This exposes:

- `POST /api/webhooks/chatwoot` — uses the default account
- `POST /api/webhooks/chatwoot/{account}` — multi-account fan-in

Every payload is verified against `chatwoot.accounts.{account}.webhook.secret`
(or the global `chatwoot.webhooks.secret`) using HMAC-SHA256 against the raw
body. Verification is **on by default**; set `verify_signature` to `false`
explicitly only when you must.

Listen for events:

```php
use BassamShoukry\LaravelChatwoot\Events\MessageCreated;

Event::listen(MessageCreated::class, function (MessageCreated $event): void {
    // $event->message is a typed Message DTO
    // $event->accountName is the account that received the webhook
});
```

Available events: `WebhookReceived`, `MessageCreated`, `MessageUpdated`,
`ConversationCreated`, `ConversationUpdated`, `ConversationStatusChanged`,
`ContactCreated`, `ContactUpdated`.

## Tracking (opt-in)

Set `CHATWOOT_TRACKING_ENABLED=true` to enable the package migrations:

- `chatwoot_contacts`
- `chatwoot_conversations`
- `chatwoot_messages`
- `chatwoot_webhook_events`

Then publish + run:

```bash
php artisan vendor:publish --tag=laravel-chatwoot-migrations
php artisan migrate
```

Persistence itself is **not automatic** — write your own listeners using the
provided Eloquent models so you control sync semantics.

## Testing

```php
use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Testing\ChatwootFake;

$fake = ChatwootFake::swap();
$fake->stub('POST', 'api/v1/accounts/1/conversations/9/messages', [
    'id' => 7, 'content' => 'hello',
]);

app(ChatwootManager::class)->messages()->send(9, 'hello');

expect($fake->calls)->toHaveCount(1);
```

Or just `Http::fake()` against the Chatwoot endpoints — the package's
`ApiClient` is a regular Laravel HTTP client.

## Security notes

- Tokens read from config are decrypted automatically when encrypted with
  `Crypt::encryptString`.
- `chatwoot.allow_local_urls` is `false` by default. Loopback hosts
  (localhost, 127.0.0.1, ::1, 0.0.0.0) and non-http(s) schemes are rejected
  during account resolution to limit SSRF.
- Outgoing logs scrub `Authorization`, `api_access_token`, `hmac_token`,
  `Cookie` and known sensitive payload keys.
- Webhook signatures are checked with `hash_equals`. Default is **strict**:
  no signature → 401.

## License

MIT — see [LICENSE.md](LICENSE.md).
