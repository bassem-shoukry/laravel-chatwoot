# Changelog

All notable changes to `bassem-shoukry/laravel-chatwoot` will be documented in this file.

## v1.1.0 - 2026-08-11

### Fixed

- `MessageResource::sendTemplate()` can now carry template parameters. It previously hardcoded `category => 'utility'`, `processed_params => (object) []` and `content: ''`, so it could only ever send a parameterless template. Added optional `$processedParams`, `$category`, `$content` — additive, existing calls still work. `processed_params` is always sent as a JSON object, never `[]` (some providers reject an array here). `$components` is now only included in the payload when non-empty.

### Added

- `ContactResource::conversations(int $contactId)` — lists a contact's conversations. Mirrors `ConversationResource::list()`'s payload unwrapping.

### Removed

- Stale `USAGE.md` — every example referenced classes that don't exist in `src/`. `README.md` is accurate and covers the same ground.

### Chores

- Bumped `actions/checkout` from v6 to v7 in CI workflows.

## v1.0.0 — Initial public release

### Breaking changes

- Full architectural redesign. The previous `LaravelChatwoot` god-class API and
  `Services/Api/*` classes are replaced by typed resource gateways under
  `BassamShoukry\LaravelChatwoot\Resources\*` and a single entry-point
  `ChatwootManager` (facade `Chatwoot`).
- `extra.laravel.aliases.LaravelChatwoot` was renamed to `Chatwoot`.
- Webhook routes are no longer auto-loaded. Apps register them by calling
  `LaravelChatwootServiceProvider::routes()`.
- Migrations are opt-in via `chatwoot.tracking.enabled`. The legacy
  `chatwoot_accounts` and `chatwoot_templates` tables were removed; account
  configuration is config-driven only.

### Added

- Typed DTOs: `Account`, `Conversation`, `Message`, `Contact`, `Inbox`,
  `Agent`, `Team`, `Label`, `Attachment`, `Pagination`, `WebhookPayload`.
- Enums: `ChannelType`, `MessageType`, `ContentType`, `ConversationStatus`,
  `EventType`.
- Typed exceptions mapping HTTP status codes (`AuthenticationException`,
  `NotFoundException`, `ValidationException`, `RateLimitException`,
  `ServerException`, `SignatureMismatchException`, `AccountNotFoundException`,
  `ConfigurationException`).
- HMAC-SHA256 signature verification with `hash_equals`, default-strict.
- `ChatwootFake` testing double + `Http::fake()`-friendly client.
- WhatsApp interactive helpers on `MessageResource`:
  `sendInteractiveButtons`, `sendInteractiveList`, `sendTemplate`, `sendRaw`.
- `ContactResource::findBySourceId()` for channels keyed by `source_id`.
- `ConversationResource::firstOrCreateForContact()`.
- SSRF guard rejecting loopback hosts unless `chatwoot.allow_local_urls=true`.
- Scrubbed request/response logging.
- Automatic retry on `429` (honors `Retry-After`) and `5xx`.

### Removed

- `RateLimitService`, `ChannelService`, `MessageService`, `InboxManager`,
  `TemplateService`, `Jobs/SendMessageJob`, `Jobs/SendBulkMessagesJob` —
  replaced by typed resources or simply deleted (apps own queueing).
- The legacy `LaravelChatwoot` god-class.
