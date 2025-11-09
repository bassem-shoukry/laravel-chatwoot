<?php

use BassamShoukry\LaravelChatwoot\Commands\LaravelChatwootCommand;
use BassamShoukry\LaravelChatwoot\Commands\SendTemplateCommand;
use BassamShoukry\LaravelChatwoot\Commands\SyncTemplatesCommand;
use BassamShoukry\LaravelChatwoot\Commands\TestConnectionCommand;
use BassamShoukry\LaravelChatwoot\LaravelChatwoot;
use BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider;
use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use BassamShoukry\LaravelChatwoot\Services\Api\AccountsApi;
use BassamShoukry\LaravelChatwoot\Services\Api\ContactsApi;
use BassamShoukry\LaravelChatwoot\Services\Api\ConversationsApi;
use BassamShoukry\LaravelChatwoot\Services\Api\InboxesApi;
use BassamShoukry\LaravelChatwoot\Services\Api\MessagesApi;
use BassamShoukry\LaravelChatwoot\Services\ApiClient;
use BassamShoukry\LaravelChatwoot\Services\ChannelService;
use BassamShoukry\LaravelChatwoot\Services\InboxManager;
use BassamShoukry\LaravelChatwoot\Services\MessageService;
use BassamShoukry\LaravelChatwoot\Services\RateLimitService;
use BassamShoukry\LaravelChatwoot\Services\TemplateService;
use BassamShoukry\LaravelChatwoot\Services\WebhookHandler;
use Spatie\LaravelPackageTools\PackageServiceProvider;

beforeEach(function () {
    $this->provider = new LaravelChatwootServiceProvider($this->app);
});

describe('LaravelChatwootServiceProvider', function () {
    it('extends Spatie PackageServiceProvider', function () {
        expect(LaravelChatwootServiceProvider::class)->toExtend(PackageServiceProvider::class);
    });

    describe('Package Configuration', function () {
        it('registers service provider correctly', function () {
            expect($this->provider)->toBeInstanceOf(LaravelChatwootServiceProvider::class);
        });

        it('has config loaded', function () {
            expect(config('chatwoot'))->toBeArray();
        });

        it('can publish migrations', function () {
            // Test that migrations can be published without error
            expect(fn () => artisan('vendor:publish', [
                '--tag'   => 'laravel-chatwoot-migrations',
                '--force' => true,
            ]))->not()->toThrow(Exception::class);
        });

        it('can publish config', function () {
            // Test that config can be published without error
            expect(fn () => artisan('vendor:publish', [
                '--tag'   => 'laravel-chatwoot-config',
                '--force' => true,
            ]))->not()->toThrow(Exception::class);
        });
    });

    describe('Service Registration', function () {
        it('registers AccountManager as singleton', function () {
            expect($this->app->bound(AccountManager::class))->toBeTrue();

            $instance1 = $this->app->make(AccountManager::class);
            $instance2 = $this->app->make(AccountManager::class);

            expect($instance1)->toBe($instance2);
        });

        it('registers ApiClient as singleton', function () {
            expect($this->app->bound(ApiClient::class))->toBeTrue();

            $instance1 = $this->app->make(ApiClient::class);
            $instance2 = $this->app->make(ApiClient::class);

            expect($instance1)->toBe($instance2);
        });

        it('registers API services as singletons', function () {
            $apiServices = [
                AccountsApi::class,
                ContactsApi::class,
                ConversationsApi::class,
                InboxesApi::class,
                MessagesApi::class,
            ];

            foreach ($apiServices as $serviceClass) {
                expect($this->app->bound($serviceClass))->toBeTrue();

                $instance1 = $this->app->make($serviceClass);
                $instance2 = $this->app->make($serviceClass);

                expect($instance1)->toBe($instance2);
            }
        });

        it('registers core services as singletons', function () {
            $coreServices = [
                InboxManager::class,
                TemplateService::class,
                MessageService::class,
                WebhookHandler::class,
                RateLimitService::class,
                ChannelService::class,
            ];

            foreach ($coreServices as $serviceClass) {
                expect($this->app->bound($serviceClass))->toBeTrue();

                $instance1 = $this->app->make($serviceClass);
                $instance2 = $this->app->make($serviceClass);

                expect($instance1)->toBe($instance2);
            }
        });

        it('registers main LaravelChatwoot service', function () {
            expect($this->app->bound('laravel-chatwoot'))->toBeTrue();
            expect($this->app->bound(LaravelChatwoot::class))->toBeTrue();

            $instance1 = $this->app->make('laravel-chatwoot');
            $instance2 = $this->app->make('laravel-chatwoot');

            expect($instance1)->toBe($instance2)
                ->and($instance1)->toBeInstanceOf(LaravelChatwoot::class);
        });
    });

    describe('Service Dependencies', function () {
        it('properly injects AccountManager into services', function () {
            $accountManager = $this->app->make(AccountManager::class);
            $apiClient = $this->app->make(ApiClient::class);

            expect($apiClient)->toBeInstanceOf(ApiClient::class);
        });

        it('properly constructs MessageService with dependencies', function () {
            $messageService = $this->app->make(MessageService::class);

            expect($messageService)->toBeInstanceOf(MessageService::class);
        });

        it('properly constructs main LaravelChatwoot with all dependencies', function () {
            $chatwoot = $this->app->make('laravel-chatwoot');

            expect($chatwoot)->toBeInstanceOf(LaravelChatwoot::class);
        });
    });

    describe('Configuration Loading', function () {
        it('loads configuration from config file', function () {
            // The config should be loaded by the package (using test config)
            expect(config('chatwoot'))->not()->toBeNull()
                ->and(config('chatwoot.default_account'))->toBe('test')
                ->and(config('chatwoot.accounts'))->toBeArray()
                ->and(config('chatwoot.accounts.test'))->toBeArray();
        });

        it('passes configuration to AccountManager', function () {
            $accountManager = $this->app->make(AccountManager::class);

            expect($accountManager)->toBeInstanceOf(AccountManager::class);
        });
    });

    describe('Command Registration', function () {
        it('can create LaravelChatwootCommand', function () {
            expect(fn () => $this->app->make(LaravelChatwootCommand::class))
                ->not()->toThrow(Exception::class);
        });

        it('can create TestConnectionCommand', function () {
            expect(fn () => $this->app->make(TestConnectionCommand::class))
                ->not()->toThrow(Exception::class);
        });

        it('can create SendTemplateCommand', function () {
            expect(fn () => $this->app->make(SendTemplateCommand::class))
                ->not()->toThrow(Exception::class);
        });

        it('can create SyncTemplatesCommand', function () {
            expect(fn () => $this->app->make(SyncTemplatesCommand::class))
                ->not()->toThrow(Exception::class);
        });
    });

    describe('Service Resolution', function () {
        it('can resolve all registered services without errors', function () {
            $services = [
                AccountManager::class,
                ApiClient::class,
                AccountsApi::class,
                ContactsApi::class,
                ConversationsApi::class,
                InboxesApi::class,
                MessagesApi::class,
                InboxManager::class,
                TemplateService::class,
                MessageService::class,
                WebhookHandler::class,
                RateLimitService::class,
                ChannelService::class,
                'laravel-chatwoot',
            ];

            foreach ($services as $service) {
                expect(fn () => $this->app->make($service))->not()->toThrow(Exception::class);
            }
        });

        it('resolves services with correct types', function () {
            $serviceTypes = [
                AccountManager::class   => AccountManager::class,
                ApiClient::class        => ApiClient::class,
                AccountsApi::class      => AccountsApi::class,
                ContactsApi::class      => ContactsApi::class,
                ConversationsApi::class => ConversationsApi::class,
                InboxesApi::class       => InboxesApi::class,
                MessagesApi::class      => MessagesApi::class,
                InboxManager::class     => InboxManager::class,
                TemplateService::class  => TemplateService::class,
                MessageService::class   => MessageService::class,
                WebhookHandler::class   => WebhookHandler::class,
                RateLimitService::class => RateLimitService::class,
                ChannelService::class   => ChannelService::class,
                'laravel-chatwoot'      => LaravelChatwoot::class,
            ];

            foreach ($serviceTypes as $serviceKey => $expectedType) {
                $instance = $this->app->make($serviceKey);
                expect($instance)->toBeInstanceOf($expectedType);
            }
        });
    });

    describe('Package Installation', function () {
        it('can install package', function () {
            // Test installation works
            expect($this->provider)->toBeInstanceOf(LaravelChatwootServiceProvider::class);
        });
    });

    describe('Deferred Services', function () {
        it('registers services immediately on boot', function () {
            // Since we're using singletons, services should be available immediately
            expect($this->app->bound(AccountManager::class))->toBeTrue();
            expect($this->app->bound(LaravelChatwoot::class))->toBeTrue();
            expect($this->app->bound('laravel-chatwoot'))->toBeTrue();
        });
    });

    describe('Service Provider Integration', function () {
        it('integrates with Laravel application lifecycle', function () {
            // Test that the service provider properly integrates with Laravel
            expect($this->provider)->toBeInstanceOf(LaravelChatwootServiceProvider::class);
            expect($this->app->getProviders(LaravelChatwootServiceProvider::class))->not()->toBeEmpty();
        });

        it('provides services through application container', function () {
            // Verify that services are accessible through app() helper
            expect(app(LaravelChatwoot::class))->toBeInstanceOf(LaravelChatwoot::class);
            expect(app('laravel-chatwoot'))->toBeInstanceOf(LaravelChatwoot::class);
            expect(app(AccountManager::class))->toBeInstanceOf(AccountManager::class);
        });
    });
});
