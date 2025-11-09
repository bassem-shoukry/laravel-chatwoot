<?php

// config for BassamShoukry/LaravelChatwoot
return [
    /*
    |--------------------------------------------------------------------------
    | Default Account
    |--------------------------------------------------------------------------
    |
    | The default Chatwoot account to use when none is specified.
    |
    */
    'default_account' => env('CHATWOOT_DEFAULT_ACCOUNT', 'primary'),

    /*
    |--------------------------------------------------------------------------
    | Chatwoot Accounts
    |--------------------------------------------------------------------------
    |
    | Configuration for multiple Chatwoot accounts. Each account can have
    | multiple inboxes with different channels and settings.
    |
    */
    'accounts' => [
        'primary' => [
            'url'           => env('CHATWOOT_PRIMARY_URL', 'https://app.chatwoot.com'),
            'token'         => env('CHATWOOT_PRIMARY_TOKEN'),
            'default_inbox' => env('CHATWOOT_PRIMARY_DEFAULT_INBOX', 'support'),
            'inboxes'       => [
                'support' => [
                    'id'          => env('CHATWOOT_PRIMARY_SUPPORT_INBOX_ID'),
                    'name'        => env('CHATWOOT_PRIMARY_SUPPORT_NAME', 'Customer Support'),
                    'channels'    => ['email', 'live_chat', 'telegram'],
                    'templates'   => ['welcome', 'follow_up', 'resolved'],
                    'rate_limits' => [
                        'per_minute' => 60,
                        'per_hour'   => 1000,
                        'per_day'    => 20000,
                    ],
                ],
                'sales' => [
                    'id'          => env('CHATWOOT_PRIMARY_SALES_INBOX_ID'),
                    'name'        => env('CHATWOOT_PRIMARY_SALES_NAME', 'Sales Team'),
                    'channels'    => ['whatsapp', 'facebook', 'instagram', 'sms'],
                    'templates'   => ['promotion', 'demo_invite', 'follow_up_sales'],
                    'rate_limits' => [
                        'per_minute' => 30,
                        'per_hour'   => 500,
                        'per_day'    => 10000,
                    ],
                ],
            ],
        ],

        'secondary' => [
            'url'           => env('CHATWOOT_SECONDARY_URL'),
            'token'         => env('CHATWOOT_SECONDARY_TOKEN'),
            'default_inbox' => env('CHATWOOT_SECONDARY_DEFAULT_INBOX', 'general'),
            'inboxes'       => [
                'general' => [
                    'id'          => env('CHATWOOT_SECONDARY_GENERAL_INBOX_ID'),
                    'name'        => env('CHATWOOT_SECONDARY_GENERAL_NAME', 'General Support'),
                    'channels'    => ['email', 'telegram', 'sms'],
                    'templates'   => ['notification', 'alert', 'announcement'],
                    'rate_limits' => [
                        'per_minute' => 20,
                        'per_hour'   => 300,
                        'per_day'    => 5000,
                    ],
                ],
                'marketing' => [
                    'id'          => env('CHATWOOT_SECONDARY_MARKETING_INBOX_ID'),
                    'name'        => env('CHATWOOT_SECONDARY_MARKETING_NAME', 'Marketing'),
                    'channels'    => ['email', 'sms', 'whatsapp'],
                    'templates'   => ['campaign', 'newsletter', 'product_update'],
                    'rate_limits' => [
                        'per_minute' => 10,
                        'per_hour'   => 200,
                        'per_day'    => 2000,
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Configurations
    |--------------------------------------------------------------------------
    |
    | Channel-specific settings and restrictions based on Chatwoot API docs.
    |
    */
    'channels' => [
        'email' => [
            'max_message_size'      => 25000, // characters
            'supports_attachments'  => true,
            'supports_templates'    => true,
            'outbound_restrictions' => 'none',
            'promotional_window'    => null, // no restrictions
        ],
        'sms' => [
            'max_message_size'      => 320, // characters for Twilio SMS
            'supports_attachments'  => false,
            'supports_templates'    => true,
            'outbound_restrictions' => 'none',
            'promotional_window'    => null, // no restrictions
        ],
        'whatsapp' => [
            'max_message_size'        => 4096, // characters for WhatsApp Cloud
            'supports_attachments'    => true,
            'supports_templates'      => true,
            'outbound_restrictions'   => 'template_only_after_24h',
            'promotional_window'      => 24, // hours
            'template_required_after' => 24, // hours
        ],
        'facebook' => [
            'max_message_size'      => 2000, // characters
            'supports_attachments'  => true,
            'supports_templates'    => true,
            'outbound_restrictions' => 'promotional_24h_or_7d_human_agent',
            'promotional_window'    => 24, // hours (7 days with human_agent tag)
            'human_agent_window'    => 168, // hours (7 days)
        ],
        'instagram' => [
            'max_message_size'      => 1000, // characters
            'supports_attachments'  => true,
            'supports_templates'    => true,
            'outbound_restrictions' => 'promotional_24h_or_7d_human_agent',
            'promotional_window'    => 24, // hours (7 days with human_agent tag)
            'human_agent_window'    => 168, // hours (7 days)
        ],
        'telegram' => [
            'max_message_size'      => 4096, // characters
            'supports_attachments'  => true,
            'supports_templates'    => true,
            'outbound_restrictions' => 'none',
            'promotional_window'    => null, // no restrictions
        ],
        'live_chat' => [
            'max_message_size'      => 10000, // characters
            'supports_attachments'  => true,
            'supports_templates'    => true,
            'outbound_restrictions' => 'verified_contacts_only',
            'promotional_window'    => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for handling incoming webhooks from Chatwoot.
    |
    */
    'webhooks' => [
        'enabled'             => env('CHATWOOT_WEBHOOKS_ENABLED', true),
        'verify_signature'    => env('CHATWOOT_VERIFY_SIGNATURE', false),
        'secret'              => env('CHATWOOT_WEBHOOK_SECRET'),
        'fire_events'         => true,
        'track_conversations' => true,
        'track_messages'      => true,
        'track_contacts'      => true,
        'auto_reply'          => [
            'enabled' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for queued message processing and background tasks.
    |
    */
    'queue' => [
        'enabled'          => env('CHATWOOT_QUEUE_ENABLED', true),
        'connection'       => env('CHATWOOT_QUEUE_CONNECTION', 'default'),
        'queue'            => env('CHATWOOT_QUEUE_NAME', 'chatwoot'),
        'retry_attempts'   => env('CHATWOOT_RETRY_ATTEMPTS', 3),
        'retry_delay'      => env('CHATWOOT_RETRY_DELAY', 60), // seconds
        'batch_size'       => env('CHATWOOT_BATCH_SIZE', 100),
        'rate_limit_delay' => env('CHATWOOT_RATE_LIMIT_DELAY', 300), // seconds to wait when rate limited
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for API client behavior, timeouts, and retries.
    |
    */
    'api' => [
        'timeout'        => env('CHATWOOT_API_TIMEOUT', 30), // seconds
        'retry_attempts' => env('CHATWOOT_API_RETRY_ATTEMPTS', 3),
        'retry_delay'    => env('CHATWOOT_API_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for caching account configurations, validations, and API responses.
    |
    */
    'cache' => [
        'store'           => env('CHATWOOT_CACHE_STORE', 'default'),
        'account_configs' => [
            'prefix' => env('CHATWOOT_CACHE_PREFIX', 'chatwoot_accounts'),
            'ttl'    => env('CHATWOOT_CACHE_TTL', 3600), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security-related settings for the package.
    |
    */
    'security' => [
        'encrypt_tokens' => env('CHATWOOT_ENCRYPT_TOKENS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for template management and processing.
    |
    */
    'templates' => [
        'storage_path' => env('CHATWOOT_TEMPLATE_PATH', resource_path('chatwoot/templates')),
    ],
];
