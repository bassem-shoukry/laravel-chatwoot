<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Account
    |--------------------------------------------------------------------------
    |
    | Name of the account configuration in `accounts` to use when no account
    | is explicitly selected on the manager.
    |
    */
    'default_account' => env('CHATWOOT_ACCOUNT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Accounts
    |--------------------------------------------------------------------------
    |
    | One entry per Chatwoot workspace this app talks to. The `account_id`
    | is Chatwoot's numeric workspace id (visible in URLs like
    | https://app.chatwoot.com/app/accounts/<id>/...). The `token` is a User
    | API access token (Profile → Access Token). Tokens are decrypted with
    | the application's key when read; you may store them encrypted using
    | `Crypt::encryptString` for defence in depth.
    |
    */
    'accounts' => [
        'default' => [
            'url'        => env('CHATWOOT_URL', 'https://app.chatwoot.com'),
            'token'      => env('CHATWOOT_API_TOKEN'),
            'account_id' => (int) env('CHATWOOT_ACCOUNT_ID', 0),

            // Optional: per-account webhook overrides. When unset, falls back to
            // the global `webhooks` block.
            'webhook' => [
                'verify_signature' => env('CHATWOOT_VERIFY_SIGNATURE', true),
                'secret'           => env('CHATWOOT_HMAC_SECRET'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Client
    |--------------------------------------------------------------------------
    */
    'api' => [
        'timeout'        => (int) env('CHATWOOT_HTTP_TIMEOUT', 30),
        'retry_attempts' => (int) env('CHATWOOT_RETRY_ATTEMPTS', 3),
        'retry_delay'    => (int) env('CHATWOOT_RETRY_DELAY_MS', 500),
        'user_agent'     => env('CHATWOOT_USER_AGENT', 'laravel-chatwoot/1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks (defaults)
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'verify_signature' => env('CHATWOOT_VERIFY_SIGNATURE', true),
        'secret'           => env('CHATWOOT_HMAC_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSRF Guard
    |--------------------------------------------------------------------------
    |
    | When false, requests to loopback hosts (localhost, 127.0.0.1, ::1) are
    | rejected during account resolution. Set to true ONLY in test
    | environments where a local Chatwoot instance is intentional.
    */
    'allow_local_urls' => (bool) env('CHATWOOT_ALLOW_LOCAL_URLS', false),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Outgoing requests/responses are scrubbed before logging. Sensitive
    | headers (Authorization, api_access_token, hmac_token, Cookie) and
    | known sensitive payload keys (token, password, secret, ...) are
    | replaced with ***REDACTED***.
    */
    'logging' => [
        'enabled'           => (bool) env('CHATWOOT_LOG_REQUESTS', false),
        'log_request_body'  => (bool) env('CHATWOOT_LOG_REQUEST_BODY', false),
        'log_response_body' => (bool) env('CHATWOOT_LOG_RESPONSE_BODY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking (opt-in)
    |--------------------------------------------------------------------------
    |
    | When enabled, the package's migrations are loaded and webhook events
    | can be persisted via the matching Eloquent models. Apps that don't
    | need local mirroring should leave this disabled.
    */
    'tracking' => [
        'enabled' => (bool) env('CHATWOOT_TRACKING_ENABLED', false),
    ],

];
