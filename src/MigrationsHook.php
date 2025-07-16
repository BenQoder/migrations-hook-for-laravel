<?php

namespace BenQoder\MigrationsHook;

/**
 * Hook Registration Helper
 */
class MigrationsHook
{
    protected static $hooks = [];

    /**
     * Register a hook for before any migration runs
     */
    public static function beforeMigrations(callable $callback, int $priority = 10)
    {
        static::addHook('migrations.started', $callback, $priority);
    }

    /**
     * Register a hook for after all migrations complete
     */
    public static function afterMigrations(callable $callback, int $priority = 10)
    {
        static::addHook('migrations.ended', $callback, $priority);
    }

    /**
     * Register a hook for before each individual migration
     */
    public static function beforeMigration(callable $callback, int $priority = 10)
    {
        static::addHook('migration.started', $callback, $priority);
    }

    /**
     * Register a hook for after each individual migration
     */
    public static function afterMigration(callable $callback, int $priority = 10)
    {
        static::addHook('migration.ended', $callback, $priority);
    }

    /**
     * Register a hook for a specific migration file
     */
    public static function forMigration(string $migrationName, callable $callback, string $when = 'after', int $priority = 10)
    {
        $hookName = $when === 'before' ? 'migration.started' : 'migration.ended';

        static::addHook($hookName, function ($data) use ($migrationName, $callback) {
            if (str_contains($data['file_name'] ?? '', $migrationName)) {
                call_user_func($callback, $data);
            }
        }, $priority);
    }

    /**
     * Register hook only for forward migrations (not rollbacks)
     */
    public static function onlyForward(callable $originalCallback): callable
    {
        return function ($data) use ($originalCallback) {
            if (($data['method'] ?? 'up') === 'up') {
                call_user_func($originalCallback, $data);
            }
        };
    }

    /**
     * Register hook only for rollbacks
     */
    public static function onlyRollback(callable $originalCallback): callable
    {
        return function ($data) use ($originalCallback) {
            if (($data['method'] ?? 'up') === 'down') {
                call_user_func($originalCallback, $data);
            }
        };
    }

    protected static function addHook(string $hookName, callable $callback, int $priority)
    {
        if (! isset(static::$hooks[$hookName])) {
            static::$hooks[$hookName] = [];
        }

        static::$hooks[$hookName][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];

        // Sort by priority
        usort(static::$hooks[$hookName], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Get hooks for a specific event
     */
    public static function getHooks(string $hookName): array
    {
        return static::$hooks[$hookName] ?? [];
    }

    /**
     * Clear all hooks (useful for testing)
     */
    public static function clearHooks()
    {
        static::$hooks = [];
    }
}
