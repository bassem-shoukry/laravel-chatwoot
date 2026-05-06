<?php

declare(strict_types=1);

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
            fn (string $modelName): string => 'BassamShoukry\\LaravelChatwoot\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelChatwootServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('chatwoot', [
            'default_account' => 'default',
            'accounts'        => [
                'default' => [
                    'url'        => 'https://chatwoot.test',
                    'token'      => 'test-token',
                    'account_id' => 1,
                    'webhook'    => [
                        'verify_signature' => false,
                        'secret'           => 'whsec_test',
                    ],
                ],
            ],
            'api' => [
                'timeout'        => 5,
                'retry_attempts' => 1,
                'retry_delay'    => 0,
                'user_agent'     => 'laravel-chatwoot-tests',
            ],
            'webhooks' => [
                'verify_signature' => false,
                'secret'           => 'whsec_test',
            ],
            'allow_local_urls' => true,
            'logging'          => [
                'enabled'           => false,
                'log_request_body'  => false,
                'log_response_body' => false,
            ],
            'tracking' => [
                'enabled' => false,
            ],
        ]);
    }
}
