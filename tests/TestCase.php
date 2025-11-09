<?php

namespace BassamShoukry\LaravelChatwoot\Tests;

use BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'BassamShoukry\\LaravelChatwoot\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelChatwootServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Note: Migrations are available but not auto-loaded to avoid setup conflicts
        // Individual tests can load migrations if needed using:
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Set up package configuration for testing (matches actual config structure)
        config()->set('chatwoot', [
            'default_account' => 'test',
            'accounts'        => [
                'primary' => [
                    'url'           => 'https://app.chatwoot.com',
                    'token'         => 'primary-token',
                    'default_inbox' => 'support',
                    'inboxes'       => [
                        'support' => [
                            'id'          => 1,
                            'name'        => 'Customer Support',
                            'channels'    => ['email', 'live_chat', 'telegram'],
                            'templates'   => ['welcome', 'follow_up', 'resolved'],
                            'rate_limits' => [
                                'per_minute' => 60,
                                'per_hour'   => 1000,
                                'per_day'    => 20000,
                            ],
                        ],
                        'sales' => [
                            'id'          => 2,
                            'name'        => 'Sales Team',
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
                    'url'           => 'https://secondary.chatwoot.com',
                    'token'         => 'secondary-token',
                    'default_inbox' => 'general',
                    'inboxes'       => [
                        'general' => [
                            'id'          => 3,
                            'name'        => 'General Support',
                            'channels'    => ['email', 'sms', 'whatsapp'],
                            'templates'   => ['welcome', 'info', 'faq'],
                            'rate_limits' => [
                                'per_minute' => 40,
                                'per_hour'   => 800,
                                'per_day'    => 15000,
                            ],
                        ],
                        'marketing' => [
                            'id'          => 4,
                            'name'        => 'Marketing',
                            'channels'    => ['email', 'facebook', 'twitter'],
                            'templates'   => ['newsletter', 'campaign', 'announcement'],
                            'rate_limits' => [
                                'per_minute' => 20,
                                'per_hour'   => 300,
                                'per_day'    => 5000,
                            ],
                        ],
                    ],
                ],
                'test' => [
                    'url'           => 'https://test.chatwoot.com',
                    'token'         => 'test-token',
                    'default_inbox' => 'test-inbox',
                    'inboxes'       => [
                        'test-inbox' => [
                            'id'          => 1,
                            'name'        => 'Test Inbox',
                            'channels'    => ['email', 'sms'],
                            'templates'   => ['welcome', 'test'],
                            'rate_limits' => [
                                'per_minute' => 10,
                                'per_hour'   => 100,
                                'per_day'    => 1000,
                            ],
                        ],
                    ],
                ],
            ],
            'channels' => [
                'email' => [
                    'max_message_size'      => 25000,
                    'supports_attachments'  => true,
                    'supports_templates'    => true,
                    'outbound_restrictions' => 'none',
                    'promotional_window'    => null,
                ],
                'sms' => [
                    'max_message_size'      => 320,
                    'supports_attachments'  => false,
                    'supports_templates'    => true,
                    'outbound_restrictions' => 'none',
                    'promotional_window'    => null,
                ],
                'whatsapp' => [
                    'max_message_size'        => 4096,
                    'supports_attachments'    => true,
                    'supports_templates'      => true,
                    'outbound_restrictions'   => 'template_only_after_24h',
                    'promotional_window'      => 24,
                    'template_required_after' => 24,
                ],
                'facebook' => [
                    'max_message_size'      => 2000,
                    'supports_attachments'  => true,
                    'supports_templates'    => true,
                    'outbound_restrictions' => 'promotional_24h_or_7d_human_agent',
                    'promotional_window'    => 24,
                    'human_agent_window'    => 168, // 7 days in hours
                ],
                'telegram' => [
                    'max_message_size'      => 4096,
                    'supports_attachments'  => true,
                    'supports_templates'    => false,
                    'outbound_restrictions' => 'none',
                    'promotional_window'    => null,
                ],
            ],
            'webhooks' => [
                'enabled'          => true,
                'verify_signature' => true,
                'endpoints'        => [
                    'conversation_created' => '/api/chatwoot/webhooks/conversation/created',
                    'message_created'      => '/api/chatwoot/webhooks/message/created',
                ],
                'events' => [
                    'conversation_created',
                    'message_created',
                ],
            ],
            'queue' => [
                'enabled'          => true,
                'connection'       => 'sync', // Expected by tests
                'queue'            => 'chatwoot',
                'retry_attempts'   => 3,
                'retry_delay'      => 60,
                'batch_size'       => 100,
                'rate_limit_delay' => 300,
            ],
            'cache' => [
                'store'  => 'array', // Expected by tests
                'tokens' => [
                    'ttl'    => 3600,
                    'prefix' => 'chatwoot_tokens',
                ],
                'templates' => [
                    'ttl'    => 1800,
                    'prefix' => 'chatwoot_templates',
                ],
                'rate_limits' => [
                    'ttl'    => 3600,
                    'prefix' => 'chatwoot_limits',
                ],
            ],
            'api' => [
                'timeout'         => 30,
                'connect_timeout' => 10,
                'retry_times'     => 3,
                'retry_delay'     => 1000,
                'verify_ssl'      => true,
                'user_agent'      => 'Laravel-Chatwoot-Package/1.0',
            ],
            'logging' => [
                'enabled'       => false, // Disable logging during tests
                'channel'       => 'single',
                'level'         => 'info',
                'log_requests'  => false,
                'log_responses' => false,
                'log_webhooks'  => false,
            ],
            'security' => [
                'encrypt_tokens'      => true,
                'encryption_key'      => 'test-encryption-key',
                'webhook_secret'      => 'test-webhook-secret',
                'allowed_webhook_ips' => '',
            ],
            'templates' => [
                'storage'           => 'database',
                'file_storage_path' => storage_path('chatwoot/templates'),
                'auto_sync'         => false,
                'validation'        => [
                    'strict_variables' => true,
                    'max_size'         => 10000,
                ],
            ],
            'development' => [
                'fake_api_responses' => true, // Enable faking for tests
                'mock_webhooks'      => true,
                'debug_mode'         => false,
                'test_account'       => 'primary',
                'test_inbox'         => 'support',
            ],
        ]);
    }
}
