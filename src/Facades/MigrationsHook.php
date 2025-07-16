<?php

namespace BenQoder\MigrationsHook\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \BenQoder\MigrationsHook\MigrationsHook
 *
 * @method static void beforeMigrations(callable $callback, int $priority = 10)
 * @method static void afterMigrations(callable $callback, int $priority = 10)
 * @method static void beforeMigration(callable $callback, int $priority = 10)
 * @method static void afterMigration(callable $callback, int $priority = 10)
 * @method static void forMigration(string $migrationName, callable $callback, string $when = 'after', int $priority = 10)
 * @method static callable onlyForward(callable $originalCallback)
 * @method static callable onlyRollback(callable $originalCallback)
 * @method static array getHooks(string $hookName)
 * @method static void clearHooks()
 */
class MigrationsHook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BenQoder\MigrationsHook\MigrationsHook::class;
    }
}
