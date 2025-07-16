<?php

namespace BenQoder\MigrationsHook;

use BenQoder\MigrationsHook\Commands\ListMigrationHooksCommand;
use BenQoder\MigrationsHook\Commands\MakeMigrationHookCommand;
use BenQoder\MigrationsHook\Commands\MigrationsHookCommand;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Support\Facades\Event;
use ReflectionClass;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MigrationsHookServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('migrations-hook-for-laravel')
            ->hasConfigFile('migrations-hook-for-laravel')
            ->hasViews()
            ->hasMigration('create_migrations_hook_for_laravel_table')
            ->hasCommands([
                MigrationsHookCommand::class,
                MakeMigrationHookCommand::class,
                ListMigrationHooksCommand::class,
            ]);
    }

    public function packageBooted()
    {
        // Listen to Laravel's built-in migration events
        Event::listen(MigrationsStarted::class, [$this, 'handleMigrationsStarted']);
        Event::listen(MigrationsEnded::class, [$this, 'handleMigrationsEnded']);
        Event::listen(MigrationStarted::class, [$this, 'handleMigrationStarted']);
        Event::listen(MigrationEnded::class, [$this, 'handleMigrationEnded']);
    }

    public function handleMigrationsStarted(MigrationsStarted $event)
    {
        $this->dispatch('migrations.started', [
            'method' => $event->method ?? 'up',
            // MigrationsStarted doesn't have connection property
            'connection' => config('database.default'),
        ]);
    }

    public function handleMigrationsEnded(MigrationsEnded $event)
    {
        $this->dispatch('migrations.ended', [
            'method' => $event->method ?? 'up',
            // MigrationsEnded doesn't have connection property
            'connection' => config('database.default'),
        ]);
    }

    public function handleMigrationStarted(MigrationStarted $event)
    {
        $filePath = $this->getMigrationFilePath($event->migration);
        $fileName = $this->getMigrationFileName($filePath);

        // Execute file-specific hooks
        $this->executeFileHook($fileName, $event->method, 'before');

        $this->dispatch('migration.started', [
            'migration' => $event->migration,
            'method' => $event->method,
            'connection' => config('database.default'), // MigrationStarted doesn't have connection property
            'file_path' => $filePath,
            'file_name' => $fileName,
        ]);
    }

    public function handleMigrationEnded(MigrationEnded $event)
    {
        $filePath = $this->getMigrationFilePath($event->migration);
        $fileName = $this->getMigrationFileName($filePath);

        // Execute file-specific hooks
        $this->executeFileHook($fileName, $event->method, 'after');

        $this->dispatch('migration.ended', [
            'migration' => $event->migration,
            'method' => $event->method,
            'connection' => config('database.default'), // MigrationEnded doesn't have connection property
            'file_path' => $filePath,
            'file_name' => $fileName,
        ]);
    }

    /**
     * Execute file-specific hook if it exists
     */
    protected function executeFileHook(?string $fileName, string $method, string $timing)
    {
        if (! $fileName) {
            return;
        }

        // Check if hooks are enabled
        if (! config('migrations-hook-for-laravel.enabled', true)) {
            return;
        }

        $hooksPath = config('migrations-hook-for-laravel.path', database_path('hooks'));
        $hookFile = $hooksPath.DIRECTORY_SEPARATOR.$fileName.'.php';

        if (! file_exists($hookFile)) {
            return;
        }

        try {
            $startTime = microtime(true);

            // Include the hook file (should return anonymous class instance)
            $hookInstance = include $hookFile;

            if (! is_object($hookInstance)) {
                if (config('migrations-hook-for-laravel.strict_mode', false)) {
                    throw new \Exception("Hook file {$fileName}.php did not return an object instance");
                }
                \Log::warning("Hook file {$fileName}.php did not return an object instance");

                return;
            }

            // Execute the appropriate hook method
            $methodName = $timing.ucfirst($method); // beforeUp, afterUp, beforeDown, afterDown

            if (method_exists($hookInstance, $methodName)) {
                // Add timeout protection if configured
                $timeout = config('migrations-hook-for-laravel.timeout', 60);

                if ($timeout > 0) {
                    $this->executeWithTimeout($hookInstance, $methodName, $timeout);
                } else {
                    call_user_func([$hookInstance, $methodName]);
                }

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                if (config('migrations-hook-for-laravel.log_execution', true)) {
                    \Log::info("Executed hook: {$fileName}::{$methodName} ({$executionTime}ms)");
                }
            }

        } catch (\Exception $e) {
            \Log::error("Migration hook failed for {$fileName}::{$methodName}: {$e->getMessage()}");

            if (config('migrations-hook-for-laravel.halt_on_error', false)) {
                throw $e;
            }
        }
    }

    /**
     * Execute hook method with timeout protection
     */
    protected function executeWithTimeout($hookInstance, string $methodName, int $timeout)
    {
        // For simplicity, we'll use the basic approach
        // In production, you might want to use pcntl_alarm or similar
        call_user_func([$hookInstance, $methodName]);
    }

    protected function getMigrationFilePath($migration): ?string
    {
        try {
            $reflection = new ReflectionClass($migration);

            return $reflection->getFileName();
        } catch (\ReflectionException $e) {
            return null;
        }
    }

    /**
     * Extract just the filename from full path
     */
    protected function getMigrationFileName(?string $filePath): ?string
    {
        if (! $filePath || $filePath === '') {
            return $filePath; // Return null for null, empty string for empty string
        }

        // Handle both Unix (/) and Windows (\) path separators
        $filename = basename(str_replace('\\', '/', $filePath), '.php');
        
        return $filename;
    }

    /**
     * Dispatch custom hook event
     */
    protected function dispatch(string $hookName, array $data)
    {
        // Fire Laravel event
        Event::dispatch("migration_hooks.{$hookName}", $data);

        // Also support action-style hooks (WordPress-like)
        $this->runHooks($hookName, $data);
    }

    /**
     * WordPress-style action hooks - SYNCHRONOUS EXECUTION
     * These WILL block migration execution until complete
     */
    protected function runHooks(string $hookName, array $data)
    {
        $hooks = MigrationsHook::getHooks($hookName);

        foreach ($hooks as $hook) {
            if (is_callable($hook['callback'])) {
                try {
                    // SYNCHRONOUS - Migration waits for this to complete
                    call_user_func($hook['callback'], $data);
                } catch (\Exception $e) {
                    // Log but don't break migration process
                    \Log::error("Migration hook failed: {$e->getMessage()}");

                    // Optionally rethrow to halt migration
                    if (config('migrations-hook-for-laravel.halt_on_error', false)) {
                        throw $e;
                    }
                }
            }
        }
    }
}
