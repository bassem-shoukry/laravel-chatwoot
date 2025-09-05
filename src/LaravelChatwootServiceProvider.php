<?php

namespace BassamShoukry\LaravelChatwoot;

use BassamShoukry\LaravelChatwoot\Commands\LaravelChatwootCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelChatwootServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-chatwoot')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_chatwoot_table')
            ->hasCommand(LaravelChatwootCommand::class);
    }
}
