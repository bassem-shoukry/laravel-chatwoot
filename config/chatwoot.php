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
        'enabled'          => env('CHATWOOT_WEBHOOKS_ENABLED', true),
        'verify_signature' => env('CHATWOOT_VERIFY_SIGNATURE', true),
        'endpoints'        => [
            'conversation_created'  => '/api/chatwoot/webhooks/conversation/created',
            'conversation_updated'  => '/api/chatwoot/webhooks/conversation/updated',
            'conversation_resolved' => '/api/chatwoot/webhooks/conversation/resolved',
            'message_created'       => '/api/chatwoot/webhooks/message/created',
            'message_updated'       => '/api/chatwoot/webhooks/message/updated',
            'contact_created'       => '/api/chatwoot/webhooks/contact/created',
            'contact_updated'       => '/api/chatwoot/webhooks/contact/updated',
        ],
        'events' => [
            'conversation_created',
            'conversation_updated',
            'conversation_resolved',
            'message_created',
            'message_updated',
            'contact_created',
            'contact_updated',
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
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for caching tokens, templates, and other frequently accessed data.
    |
    */
    'cache' => [
        'store'  => env('CHATWOOT_CACHE_STORE', 'default'),
        'tokens' => [
            'ttl'    => env('CHATWOOT_TOKEN_CACHE_TTL', 3600), // seconds
            'prefix' => 'chatwoot_tokens',
        ],
        'templates' => [
            'ttl'    => env('CHATWOOT_TEMPLATE_CACHE_TTL', 1800), // seconds
            'prefix' => 'chatwoot_templates',
        ],
        'rate_limits' => [
            'ttl'    => env('CHATWOOT_RATE_LIMIT_CACHE_TTL', 3600), // seconds
            'prefix' => 'chatwoot_limits',
        ],
        'account_configs' => [
            'ttl'    => env('CHATWOOT_ACCOUNT_CONFIG_CACHE_TTL', 7200), // seconds
            'prefix' => 'chatwoot_accounts',
        ],
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
        'timeout'         => env('CHATWOOT_API_TIMEOUT', 30), // seconds
        'connect_timeout' => env('CHATWOOT_API_CONNECT_TIMEOUT', 10), // seconds
        'retry_times'     => env('CHATWOOT_API_RETRY_TIMES', 3),
        'retry_delay'     => env('CHATWOOT_API_RETRY_DELAY', 1000), // milliseconds
        'verify_ssl'      => env('CHATWOOT_VERIFY_SSL', true),
        'user_agent'      => env('CHATWOOT_USER_AGENT', 'Laravel-Chatwoot-Package/1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for logging API requests, responses, and errors.
    |
    */
    'logging' => [
        'enabled'       => env('CHATWOOT_LOGGING_ENABLED', true),
        'channel'       => env('CHATWOOT_LOG_CHANNEL', 'single'),
        'level'         => env('CHATWOOT_LOG_LEVEL', 'info'),
        'log_requests'  => env('CHATWOOT_LOG_REQUESTS', false),
        'log_responses' => env('CHATWOOT_LOG_RESPONSES', false),
        'log_webhooks'  => env('CHATWOOT_LOG_WEBHOOKS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security-related settings for token encryption and webhook verification.
    |
    */
    'security' => [
        'encrypt_tokens'      => env('CHATWOOT_ENCRYPT_TOKENS', true),
        'encryption_key'      => env('CHATWOOT_ENCRYPTION_KEY', env('APP_KEY')),
        'webhook_secret'      => env('CHATWOOT_WEBHOOK_SECRET'),
        'allowed_webhook_ips' => env('CHATWOOT_ALLOWED_WEBHOOK_IPS', ''), // comma-separated IPs
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
        'storage'           => env('CHATWOOT_TEMPLATE_STORAGE', 'database'), // database, file, cache
        'file_storage_path' => env('CHATWOOT_TEMPLATE_PATH', storage_path('chatwoot/templates')),
        'auto_sync'         => env('CHATWOOT_AUTO_SYNC_TEMPLATES', false),
        'validation'        => [
            'strict_variables' => env('CHATWOOT_STRICT_TEMPLATE_VARIABLES', true),
            'max_size'         => env('CHATWOOT_MAX_TEMPLATE_SIZE', 10000), // characters
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for development and testing environments.
    |
    */
    'development' => [
        'fake_api_responses' => env('CHATWOOT_FAKE_API_RESPONSES', false),
        'mock_webhooks'      => env('CHATWOOT_MOCK_WEBHOOKS', false),
        'debug_mode'         => env('CHATWOOT_DEBUG_MODE', false),
        'test_account'       => env('CHATWOOT_TEST_ACCOUNT', 'primary'),
        'test_inbox'         => env('CHATWOOT_TEST_INBOX', 'support'),
    ],
];
