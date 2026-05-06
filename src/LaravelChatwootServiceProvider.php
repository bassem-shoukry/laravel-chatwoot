<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot;

use BassamShoukry\LaravelChatwoot\Contracts\AccountResolver;
use BassamShoukry\LaravelChatwoot\Contracts\SignatureVerifier;
use BassamShoukry\LaravelChatwoot\Contracts\TokenVault;
use BassamShoukry\LaravelChatwoot\Support\AccountManager;
use BassamShoukry\LaravelChatwoot\Support\CryptTokenVault;
use BassamShoukry\LaravelChatwoot\Webhooks\HmacSignatureVerifier;
use BassamShoukry\LaravelChatwoot\Webhooks\WebhookController;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Route;
use Psr\Log\LoggerInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelChatwootServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-chatwoot')
            ->hasConfigFile('chatwoot');

        if ((bool) config('chatwoot.tracking.enabled', false)) {
            $package
                ->hasMigrations([
                    'create_chatwoot_contacts_table',
                    'create_chatwoot_conversations_table',
                    'create_chatwoot_messages_table',
                    'create_chatwoot_webhook_events_table',
                ]);
        }
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TokenVault::class, static fn (Application $app): TokenVault => new CryptTokenVault(
            $app->make(StringEncrypter::class),
        ));

        $this->app->singleton(AccountResolver::class, static fn (Application $app): AccountResolver => new AccountManager(
            $app->make(ConfigRepository::class),
            $app->make(TokenVault::class),
        ));

        $this->app->singleton(SignatureVerifier::class, HmacSignatureVerifier::class);

        $this->app->singleton(ChatwootManager::class, static fn (Application $app): ChatwootManager => new ChatwootManager(
            $app->make(AccountResolver::class),
            $app->make(HttpFactory::class),
            $app->make(ConfigRepository::class),
            $app->make(LoggerInterface::class),
        ));
    }

    public function packageBooted(): void
    {
        // Routes are opt-in via Chatwoot::routes(); apps choose prefix/middleware.
    }

    /**
     * Register the package webhook routes.
     *
     * Apps call \BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider::routes()
     * inside their RouteServiceProvider or routes file.
     *
     * @param array<int, string> $middleware
     */
    public static function routes(string $prefix = 'api/webhooks/chatwoot', array $middleware = ['api']): void
    {
        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(function (): void {
                Route::post('/', WebhookController::class)
                    ->name('chatwoot.webhook');

                Route::post('/{account}', WebhookController::class)
                    ->name('chatwoot.webhook.account');
            });
    }
}
