<?php

namespace BassamShoukry\LaravelChatwoot\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \BassamShoukry\LaravelChatwoot\LaravelChatwoot
 */
class LaravelChatwoot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BassamShoukry\LaravelChatwoot\LaravelChatwoot::class;
    }
}
