<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

it('offers the migrations for publishing without the config file being published first', function (): void {
    // TestCase sets chatwoot.tracking.enabled=false, as an app that only set
    // the env var (config not yet published) would see during register().
    $files = ServiceProvider::pathsToPublish(LaravelChatwootServiceProvider::class, 'chatwoot-migrations');

    expect($files)->toHaveCount(4);
});

it('never runs the package migrations by itself', function (): void {
    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('chatwoot_contacts'))->toBeFalse();
});

it('documents publish tags that actually exist', function (): void {
    $readme = (string) file_get_contents(__DIR__ . '/../../README.md');
    preg_match_all('/vendor:publish --tag=([\w-]+)/', $readme, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $tag) {
        expect(ServiceProvider::pathsToPublish(LaravelChatwootServiceProvider::class, $tag))
            ->not->toBeEmpty("README tag [{$tag}] publishes nothing");
    }
});
