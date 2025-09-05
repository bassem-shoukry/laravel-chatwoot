<?php

namespace BassamShoukry\LaravelChatwoot\Commands;

use Illuminate\Console\Command;

class LaravelChatwootCommand extends Command
{
    public $signature = 'laravel-chatwoot';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
