<?php

namespace BenQoder\MigrationsHook\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ListMigrationHooksCommand extends Command
{
    protected $signature = 'migration-hooks:list {--missing : Show migrations without hooks}';

    protected $description = 'List all migration hook files';

    public function handle()
    {
        $hooksPath = config('migrations-hook-for-laravel.path', database_path('hooks'));
        $migrationsPath = database_path('migrations');

        if (! is_dir($hooksPath)) {
            $this->error('Hooks directory does not exist: '.$hooksPath);

            return 1;
        }

        // Get all migration files
        $migrationFiles = collect(File::files($migrationsPath))
            ->map(function ($file) {
                return $file->getFilenameWithoutExtension();
            })
            ->sort();

        // Get all hook files
        $hookFiles = collect(File::files($hooksPath))
            ->map(function ($file) {
                return $file->getFilenameWithoutExtension();
            })
            ->sort();

        if ($this->option('missing')) {
            $this->showMissingHooks($migrationFiles, $hookFiles);
        } else {
            $this->showAllHooks($migrationFiles, $hookFiles);
        }

        return 0;
    }

    private function showAllHooks($migrationFiles, $hookFiles)
    {
        $this->info('Migration Hooks Status:');
        $this->line('');

        $headers = ['Migration', 'Hook Exists', 'Hook Methods'];
        $rows = [];

        foreach ($migrationFiles as $migration) {
            $hasHook = $hookFiles->contains($migration);
            $methods = [];

            if ($hasHook) {
                $methods = $this->getHookMethods($migration);
            }

            $rows[] = [
                $migration,
                $hasHook ? '✓' : '✗',
                $hasHook ? implode(', ', $methods) : '-',
            ];
        }

        $this->table($headers, $rows);

        $totalMigrations = $migrationFiles->count();
        $migrationsWithHooks = $hookFiles->count();

        $this->line('');
        $this->info("Total migrations: {$totalMigrations}");
        $this->info("Migrations with hooks: {$migrationsWithHooks}");
        
        if ($totalMigrations > 0) {
            $this->info('Coverage: '.round(($migrationsWithHooks / $totalMigrations) * 100, 1).'%');
        } else {
            $this->info('Coverage: 0%');
        }
    }

    private function showMissingHooks($migrationFiles, $hookFiles)
    {
        $missing = $migrationFiles->diff($hookFiles);

        if ($missing->isEmpty()) {
            $this->info('All migrations have hook files!');

            return;
        }

        $this->info('Migrations without hooks:');
        $this->line('');

        foreach ($missing as $migration) {
            $this->line("  • {$migration}");
        }

        $this->line('');
        $this->info('To create a hook file, run:');
        $this->comment('php artisan make:migration-hook <migration-name>');
    }

    private function getHookMethods($migrationName): array
    {
        $hooksPath = config('migrations-hook-for-laravel.path', database_path('hooks'));
        $hookFile = $hooksPath.DIRECTORY_SEPARATOR.$migrationName.'.php';

        if (! file_exists($hookFile)) {
            return [];
        }

        try {
            $hookInstance = include $hookFile;

            if (! is_object($hookInstance)) {
                return ['Invalid hook file'];
            }

            $methods = [];
            $possibleMethods = ['beforeUp', 'afterUp', 'beforeDown', 'afterDown'];

            foreach ($possibleMethods as $method) {
                if (method_exists($hookInstance, $method)) {
                    $methods[] = $method;
                }
            }

            return $methods;
        } catch (\Exception $e) {
            return ['Error: '.$e->getMessage()];
        }
    }
}
