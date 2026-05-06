<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Contracts;

use BassamShoukry\LaravelChatwoot\Support\AccountContext;

interface AccountResolver
{
    public function resolve(?string $name = null): AccountContext;
}
