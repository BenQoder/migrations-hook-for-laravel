<?php

namespace BenQoder\MigrationsHook\Commands;

use Illuminate\Console\Command;

class MigrationsHookCommand extends Command
{
    public $signature = 'migrations-hook-for-laravel';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
