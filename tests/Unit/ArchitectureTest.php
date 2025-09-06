<?php

describe('Basic Package Architecture', function () {
    it('ensures facade extends Laravel Facade', function () {
        expect(\BassamShoukry\LaravelChatwoot\Facades\LaravelChatwoot::class)
            ->toExtend(\Illuminate\Support\Facades\Facade::class);
    });

    it('ensures service provider extends Spatie package provider', function () {
        expect(\BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider::class)
            ->toExtend(\Spatie\LaravelPackageTools\PackageServiceProvider::class);
    });

    it('ensures main class is properly structured', function () {
        expect(\BassamShoukry\LaravelChatwoot\LaravelChatwoot::class)
            ->toBeClass()
            ->not()->toExtend(\Illuminate\Support\Facades\Facade::class);
    });
});

describe('Code Quality', function () {
    it('ensures facade has proper accessor', function () {
        expect(\BassamShoukry\LaravelChatwoot\Facades\LaravelChatwoot::class)
            ->toHaveMethod('getFacadeAccessor');
    });

    it('ensures service provider has required methods', function () {
        expect(\BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider::class)
            ->toHaveMethod('configurePackage')
            ->toHaveMethod('packageRegistered');
    });

    it('ensures main service class has constructor', function () {
        expect(\BassamShoukry\LaravelChatwoot\LaravelChatwoot::class)
            ->toHaveMethod('__construct');
    });
});