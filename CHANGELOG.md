# Changelog

All notable changes to `bassem-shoukry/laravel-chatwoot` will be documented in this file.

## v2.0.1 - 2026-11-24

### Breaking

- Laravel 11 is no longer supported (`illuminate/*` is now `^12.0|^13.0`).
  Composer refuses to install any `laravel/framework` 11.x release because
  every one is affected by published security advisories, so it could not be
  tested.

### Chores

- CI no longer runs on tag pushes (the tagged commit was already tested on its
  branch), and the code-style job checks out the pushed branch instead of a
  detached HEAD.
- Dev only: `symfony/translation` is floored at `^7.2` so `--prefer-lowest`
  runs don't print a PHP 8.4 deprecation from `symfony/translation` 6.4.0, and
  PHPStan runs with `--memory-limit=1G`.

## v2.0.0 - 2026-11-24

### Breaking (behaviour changes; the only signature change is an optional new parameter on `ChatwootFake::swap()`)

- `POST` and `PATCH` are no longer retried on `5xx` or connection errors
  (including timeouts), because a retry could deliver the same message twice.
  `429` is still retried. Set `chatwoot.api.retry_non_idempotent=true` for the
  old behaviour.
- A `Retry-After` longer than `chatwoot.api.max_retry_after` (default 60s) now
  throws `RateLimitException` immediately instead of sleeping.
- A `2xx` response with a non-empty body that is not JSON now throws
  `ChatwootException` instead of returning `[]`.
- HTTP redirects are no longer followed; a `3xx` now raises `ChatwootException`.
- `ChatwootClient` calls with an absolute URL on a different origin than the
  account URL throw `ChatwootException` instead of sending the token there.
- Account URLs: `127.0.0.0/8`, `0.0.0.0/8`, `169.254.0.0/16`, `::`, `fe80::/10`,
  IPv4-mapped loopback and numeric shorthands are now rejected unless
  `allow_local_urls=true`; non-http(s) or hostless URLs are rejected even with
  it.
- A token that looks like a Laravel ciphertext but fails to decrypt throws
  `ConfigurationException` instead of being sent as the token.
- Webhooks: a request carrying `X-Chatwoot-Timestamp` is verified against
  `"{timestamp}.{body}"` and rejected (`401 stale_timestamp`) outside
  `chatwoot.webhooks.timestamp_tolerance` (default 300s). The dispatched
  payload is the decoded JSON body only; query-string parameters are no longer
  merged in, and a non-JSON body returns `400`.
- Webhook account names are matched as `accounts` array keys, so a dotted
  route segment such as `/default.webhook` now returns `404`.
- `ChatwootException::context()['body']` has secret-looking keys redacted.
- Logged request/response bodies (when body logging is on) now also redact
  `provider_config` and every `*_token`, `*_secret`, `*_password` and
  `*_api_key` key.
- `ChatwootManager::account()` clones share one client registry with the root
  manager, so `setClient()` on a clone is visible everywhere (it used to stay
  local to the clone).
- Account hosts written as numeric shorthands (`127.1`, `2130706433`,
  `0x7f000001`, and also private ones such as `10.1`) are rejected unless
  `allow_local_urls=true`; use the dotted form.
- The shipped config's `default` account no longer sets its own `webhook`
  block, so it inherits the global `webhooks` settings. Already-published
  configs are unaffected.

### Fixed

- Webhook signatures from Chatwoot (`X-Chatwoot-Signature: sha256=…` over
  `"{X-Chatwoot-Timestamp}.{body}"`) were rejected with `401`; they now verify.
- Duplicate WhatsApp/template messages when a `POST` hit a `502` or timed out.
- The API token leaked to other hosts through absolute URLs and through
  redirects (Guzzle keeps the custom `api_access_token` header cross-host).
- Logs and exception context no longer expose `hmac_token`, `pubsub_token`,
  `page_access_token`, `webhook_secret`, `provider_config` and similar keys.
- Account config changed at runtime (tenant switch, token rotation) is picked
  up without a worker restart.
- `ChatwootFake::swap()` works without any Chatwoot credentials configured,
  and `setClient()` on an `account()` clone reaches the root manager.
- `CHATWOOT_TRACKING_ENABLED=true` without a published config never offered the
  migrations (the provider read the flag before the package config was merged).
  They are now offered unless the app explicitly sets `tracking.enabled=false`.
  The README now names the real tags (`chatwoot-config`, `chatwoot-migrations`).

### Added

- Config: `api.retry_non_idempotent`, `api.max_retry_after`,
  `webhooks.timestamp_tolerance` (also per account under `webhook`).
- `ChatwootFake::swap(..., bool $preventStrayRequests = true)`.

### Chores

- CI tests Laravel 13; dev dependency `orchestra/testbench` widened to
  `^9.0|^10.0|^11.1`.

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
