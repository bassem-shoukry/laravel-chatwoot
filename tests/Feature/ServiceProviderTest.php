<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Register a fresh provider instance with the given tracking flag, the way
 * the app boots it (register() runs configurePackage()).
 *
 * @return array<string, string>
 */
function chatwootMigrationsPublishedWith(?bool $tracking): array
{
    ServiceProvider::$publishes = [];
    ServiceProvider::$publishGroups = [];
    config()->set('chatwoot.tracking.enabled', $tracking);

    $provider = new LaravelChatwootServiceProvider(app());
    $provider->register();
    $provider->boot();

    return ServiceProvider::pathsToPublish(LaravelChatwootServiceProvider::class, 'chatwoot-migrations');
}

it('offers the migrations when the config file is not published (flag reads null)', function (): void {
    expect(chatwootMigrationsPublishedWith(null))->toHaveCount(4);
});

it('offers the migrations when tracking is enabled', function (): void {
    expect(chatwootMigrationsPublishedWith(true))->toHaveCount(4);
});

it('keeps the migrations out when the app explicitly disables tracking', function (): void {
    expect(chatwootMigrationsPublishedWith(false))->toBe([]);
});

it('never runs the package migrations by itself', function (): void {
    chatwootMigrationsPublishedWith(true);

    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('chatwoot_contacts'))->toBeFalse();
});

it('documents publish tags that actually exist', function (): void {
    chatwootMigrationsPublishedWith(null);

    $readme = (string) file_get_contents(__DIR__ . '/../../README.md');
    preg_match_all('/vendor:publish --tag=([\w-]+)/', $readme, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $tag) {
        expect(ServiceProvider::pathsToPublish(LaravelChatwootServiceProvider::class, $tag))
            ->not->toBeEmpty("README tag [{$tag}] publishes nothing");
    }
});
