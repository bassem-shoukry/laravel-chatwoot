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
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Load migrations if they exist
        $migrationPath = __DIR__ . '/../database/migrations';
        if (is_dir($migrationPath)) {
            foreach (glob($migrationPath . '/*.php') as $migration) {
                $this->loadMigrationsFrom($migration);
            }
        }

        // Set up package configuration for testing
        config()->set('chatwoot', [
            'default_account' => 'test',
            'accounts' => [
                'test' => [
                    'url' => 'https://test.chatwoot.com',
                    'token' => 'test-token',
                    'default_inbox' => 'test-inbox',
                    'inboxes' => [
                        'test-inbox' => [
                            'id' => 1,
                            'name' => 'Test Inbox',
                            'channels' => ['email', 'sms'],
                            'templates' => ['welcome', 'test'],
                            'rate_limits' => [
                                'per_minute' => 10,
                                'per_hour' => 100,
                                'per_day' => 1000,
                            ],
                        ],
                    ],
                ],
            ],
            'channels' => [
                'email' => [
                    'max_message_size' => 25000,
                    'supports_attachments' => true,
                    'supports_templates' => true,
                    'outbound_restrictions' => 'none',
                    'promotional_window' => null,
                ],
                'sms' => [
                    'max_message_size' => 320,
                    'supports_attachments' => false,
                    'supports_templates' => true,
                    'outbound_restrictions' => 'none',
                    'promotional_window' => null,
                ],
            ],
            'webhooks' => [
                'enabled' => true,
                'verify_signature' => true,
                'endpoints' => [
                    'conversation_created' => '/api/chatwoot/webhooks/conversation/created',
                    'message_created' => '/api/chatwoot/webhooks/message/created',
                ],
                'events' => [
                    'conversation_created',
                    'message_created',
                ],
            ],
            'queue' => [
                'enabled' => true,
                'connection' => 'sync', // Use sync for testing
                'queue' => 'chatwoot',
                'retry_attempts' => 3,
                'retry_delay' => 60,
                'batch_size' => 100,
                'rate_limit_delay' => 300,
            ],
            'cache' => [
                'store' => 'array', // Use array cache for testing
                'tokens' => [
                    'ttl' => 3600,
                    'prefix' => 'chatwoot_tokens',
                ],
                'templates' => [
                    'ttl' => 1800,
                    'prefix' => 'chatwoot_templates',
                ],
                'rate_limits' => [
                    'ttl' => 3600,
                    'prefix' => 'chatwoot_limits',
                ],
            ],
            'api' => [
                'timeout' => 30,
                'connect_timeout' => 10,
                'retry_times' => 3,
                'retry_delay' => 1000,
                'verify_ssl' => true,
                'user_agent' => 'Laravel-Chatwoot-Package/1.0',
            ],
            'logging' => [
                'enabled' => false, // Disable logging during tests
                'channel' => 'single',
                'level' => 'info',
                'log_requests' => false,
                'log_responses' => false,
                'log_webhooks' => false,
            ],
            'security' => [
                'encrypt_tokens' => true,
                'encryption_key' => 'test-encryption-key',
                'webhook_secret' => 'test-webhook-secret',
                'allowed_webhook_ips' => '',
            ],
            'templates' => [
                'storage' => 'database',
                'file_storage_path' => storage_path('chatwoot/templates'),
                'auto_sync' => false,
                'validation' => [
                    'strict_variables' => true,
                    'max_size' => 10000,
                ],
            ],
            'development' => [
                'fake_api_responses' => true, // Enable faking for tests
                'mock_webhooks' => true,
                'debug_mode' => false,
                'test_account' => 'test',
                'test_inbox' => 'test-inbox',
            ],
        ]);
    }
}
