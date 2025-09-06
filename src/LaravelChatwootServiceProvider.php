<?php

namespace BassamShoukry\LaravelChatwoot;

use BassamShoukry\LaravelChatwoot\Commands\LaravelChatwootCommand;
use BassamShoukry\LaravelChatwoot\Commands\SendTemplateCommand;
use BassamShoukry\LaravelChatwoot\Commands\SyncTemplatesCommand;
use BassamShoukry\LaravelChatwoot\Commands\TestConnectionCommand;
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
use Spatie\LaravelPackageTools\Commands\InstallCommand as SpatieInstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelChatwootServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-chatwoot')
            ->hasConfigFile()
            ->hasRoute('api')
            ->hasMigrations([
                'create_chatwoot_accounts_table',
                'create_chatwoot_templates_table',
                'create_chatwoot_messages_table',
                'create_chatwoot_webhook_events_table',
                'create_chatwoot_conversations_table',
                'create_chatwoot_contacts_table',
            ])
            ->hasCommands([
                LaravelChatwootCommand::class,
                TestConnectionCommand::class,
                SendTemplateCommand::class,
                SyncTemplatesCommand::class,
            ])
            ->hasInstallCommand(function (SpatieInstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('bassamshoukry/laravel-chatwoot');
            });
    }

    public function packageRegistered(): void
    {
        // Register services
        $this->app->singleton(AccountManager::class, function ($app) {
            return new AccountManager($app['config']['chatwoot']);
        });

        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient($app->make(AccountManager::class));
        });

        // Register individual API service classes
        $this->app->singleton(AccountsApi::class, function ($app) {
            return new AccountsApi($app->make(AccountManager::class));
        });

        $this->app->singleton(ContactsApi::class, function ($app) {
            return new ContactsApi($app->make(AccountManager::class));
        });

        $this->app->singleton(ConversationsApi::class, function ($app) {
            return new ConversationsApi($app->make(AccountManager::class));
        });

        $this->app->singleton(InboxesApi::class, function ($app) {
            return new InboxesApi($app->make(AccountManager::class));
        });

        $this->app->singleton(MessagesApi::class, function ($app) {
            return new MessagesApi($app->make(AccountManager::class));
        });

        $this->app->singleton(InboxManager::class, function ($app) {
            return new InboxManager($app->make(AccountManager::class));
        });

        $this->app->singleton(TemplateService::class, function ($app) {
            return new TemplateService;
        });

        $this->app->singleton(MessageService::class, function ($app) {
            return new MessageService(
                $app->make(AccountManager::class),
                $app->make(InboxManager::class),
                $app->make(TemplateService::class),
                $app->make(ApiClient::class)
            );
        });

        $this->app->singleton(WebhookHandler::class, function ($app) {
            return new WebhookHandler($app->make(AccountManager::class));
        });

        $this->app->singleton(RateLimitService::class, function ($app) {
            return new RateLimitService;
        });

        $this->app->singleton(ChannelService::class, function ($app) {
            return new ChannelService;
        });

        $this->app->singleton('laravel-chatwoot', function ($app) {
            return new LaravelChatwoot(
                $app->make(AccountManager::class),
                $app->make(InboxManager::class),
                $app->make(MessageService::class),
                $app->make(TemplateService::class),
                $app->make(RateLimitService::class),
                $app->make(WebhookHandler::class)
            );
        });
    }
}
