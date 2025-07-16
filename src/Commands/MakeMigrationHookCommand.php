<?php

namespace BenQoder\MigrationsHook\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeMigrationHookCommand extends Command
{
    protected $signature = 'make:migration-hook {migration}';

    protected $description = 'Create a migration hook file (Laravel-style anonymous class)';

    public function handle()
    {
        $migrationName = $this->argument('migration');

        // Remove .php extension if provided
        $migrationName = Str::replaceLast('.php', '', $migrationName);

        // Validate that the migration file exists
        if (!$this->migrationExists($migrationName)) {
            $this->error("Migration file not found: {$migrationName}");
            $this->info('Available migrations:');
            $this->listAvailableMigrations();
            return 1;
        }

        $hooksPath = config('migrations-hook-for-laravel.path', database_path('hooks'));

        // Create hooks directory if it doesn't exist
        if (! is_dir($hooksPath)) {
            mkdir($hooksPath, 0755, true);
            $this->info('Created hooks directory: '.$hooksPath);
        }

        $hookFile = $hooksPath.DIRECTORY_SEPARATOR.$migrationName.'.php';

        if (file_exists($hookFile)) {
            $this->error('Hook file already exists: '.$hookFile);

            return 1;
        }

        $stub = $this->getStub();
        $content = str_replace('{{migration_name}}', $migrationName, $stub);

        file_put_contents($hookFile, $content);

        $this->info('Migration hook created: '.$hookFile);

        return 0;
    }

    protected function getStub(): string
    {
        // Always use Laravel-style anonymous class
        return $this->getLaravelStyleStub();
    }

    protected function getLaravelStyleStub(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Migration Hook for: {{migration_name}}
 * 
 * This hook will be automatically executed when the migration runs.
 * Available methods: beforeUp, afterUp, beforeDown, afterDown
 */
return new class {
    
    /**
     * Execute before migration up
     */
    public function beforeUp()
    {
        Log::info('Before migration up: {{migration_name}}');
        
        // Your code here - runs BEFORE the migration
        // Example: Check prerequisites, backup data, etc.
        
        // Example: Check if required table exists
        // if (!Schema::hasTable('required_table')) {
        //     throw new Exception('Required table does not exist');
        // }
    }
    
    /**
     * Execute after migration up
     */
    public function afterUp()
    {
        Log::info('After migration up: {{migration_name}}');
        
        // Your code here - runs AFTER the migration
        // Example: Seed data, clear cache, update search index, etc.
        
        // Example: Seed initial data
        // DB::table('users')->insert([
        //     'name' => 'Administrator',
        //     'email' => 'admin@example.com',
        //     'password' => Hash::make('password'),
        //     'created_at' => now(),
        //     'updated_at' => now(),
        // ]);
        
        // Example: Clear cache
        // Cache::flush();
        // Artisan::call('cache:clear');
    }
    
    /**
     * Execute before migration down (rollback)
     */
    public function beforeDown()
    {
        Log::info('Before migration down: {{migration_name}}');
        
        // Your code here - runs BEFORE rollback
        // Example: Backup data that will be lost
        
        // Example: Backup important data before rollback
        // $importantData = DB::table('users')->where('role', 'admin')->get();
        // Cache::put('admin_backup_{{migration_name}}', $importantData, 3600);
    }
    
    /**
     * Execute after migration down (rollback)
     */
    public function afterDown()
    {
        Log::info('After migration down: {{migration_name}}');
        
        // Your code here - runs AFTER rollback
        // Example: Cleanup, restore backups, etc.
        
        // Example: Restore backed up data
        // $backup = Cache::get('admin_backup_{{migration_name}}');
        // if ($backup) {
        //     // Restore logic here
        //     Cache::forget('admin_backup_{{migration_name}}');
        // }
    }
};
PHP;
    }

    /**
     * Check if a migration file exists
     */
    protected function migrationExists(string $migrationName): bool
    {
        $migrationPaths = $this->getMigrationPaths();
        
        foreach ($migrationPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            
            $files = glob($path . '/*.php');
            foreach ($files as $file) {
                $filename = basename($file, '.php');
                
                // Only match the full filename (including timestamp)
                if ($filename === $migrationName) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Get all migration paths
     */
    protected function getMigrationPaths(): array
    {
        return array_merge(
            [$this->laravel->databasePath('migrations')],
            $this->getMigrator()->paths()
        );
    }

    /**
     * Get the migrator instance
     */
    protected function getMigrator()
    {
        return $this->laravel['migrator'];
    }

    /**
     * List all available migrations for user reference
     */
    protected function listAvailableMigrations(): void
    {
        $migrations = [];
        $migrationPaths = $this->getMigrationPaths();
        
        foreach ($migrationPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            
            $files = glob($path . '/*.php');
            foreach ($files as $file) {
                $filename = basename($file, '.php');
                $migrations[] = $filename;
            }
        }

        if (empty($migrations)) {
            $this->warn('No migration files found.');
            return;
        }

        $this->info('Available migration files:');
        foreach ($migrations as $migration) {
            $this->line("  - {$migration}");
        }
        
        $this->info('');
        $this->info('Usage: php artisan make:migration-hook <full_migration_filename>');
        $this->info('Example: php artisan make:migration-hook 2024_01_15_123456_create_users_table');
    }
}
