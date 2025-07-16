# Migration Hooks for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/benqoder/migrations-hook-for-laravel.svg?style=flat-square)](https://packagist.org/packages/benqoder/migrations-hook-for-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/benqoder/migrations-hook-for-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/benqoder/migrations-hook-for-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/benqoder/migrations-hook-for-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/benqoder/migrations-hook-for-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/benqoder/migrations-hook-for-laravel.svg?style=flat-square)](https://packagist.org/packages/benqoder/migrations-hook-for-laravel)

A powerful Laravel package that allows you to hook into migration events and execute custom code before and after migrations run. This package provides multiple ways to register migration hooks, making it perfect for cache clearing, data seeding, search index updates, and other post-migration tasks.

## Features

- 🔥 **File-based hooks** - Create individual hook files for specific migrations
- 🎯 **Event-based hooks** - Register hooks programmatically for specific events
- ⚡ **Multiple hook types** - beforeUp, afterUp, beforeDown, afterDown
- 🔧 **Configurable** - Timeout protection, error handling, logging
- 📊 **Management commands** - List hooks, create new hooks easily
- 🎪 **Flexible registration** - WordPress-style hooks and Laravel events
- 🛡️ **Error handling** - Configurable halt-on-error behavior
- 📝 **Comprehensive logging** - Track hook execution with timing

## Installation

You can install the package via composer:

```bash
composer require benqoder/migrations-hook-for-laravel
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="migrations-hook-for-laravel-config"
```

This is the contents of the published config file:

```php
return [
    'enabled' => env('MIGRATION_HOOKS_ENABLED', true),
    'path' => env('MIGRATION_HOOKS_PATH', database_path('hooks')),
    'halt_on_error' => env('MIGRATION_HOOKS_HALT_ON_ERROR', false),
    'strict_mode' => env('MIGRATION_HOOKS_STRICT_MODE', false),
    'log_execution' => env('MIGRATION_HOOKS_LOG_EXECUTION', true),
    'timeout' => env('MIGRATION_HOOKS_TIMEOUT', 60),
];
```

## Usage

### File-based Hooks

Create migration-specific hook files that automatically execute when migrations run:

```bash
php artisan make:migration-hook create_users_table
```

This creates a hook file in `database/hooks/create_users_table.php`:

```php
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class {
    public function beforeUp()
    {
        Log::info('Before creating users table');
        // Check prerequisites, backup data, etc.
    }
    
    public function afterUp()
    {
        Log::info('After creating users table');
        
        // Seed initial admin user
        DB::table('users')->insert([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Clear cache
        Cache::flush();
    }
    
    public function beforeDown()
    {
        // Backup important data before rollback
        $adminUsers = DB::table('users')->where('role', 'admin')->get();
        Cache::put('admin_backup', $adminUsers, 3600);
    }
    
    public function afterDown()
    {
        // Cleanup after rollback
        Cache::forget('admin_backup');
    }
};
```

### Programmatic Hooks

Register hooks in your `AppServiceProvider` or any service provider:

```php
use BenQoder\MigrationsHook\Facades\MigrationsHook;

public function boot()
{
    // Clear cache after all migrations
    MigrationsHook::afterMigrations(function ($data) {
        if ($data['method'] === 'up') {
            Artisan::call('cache:clear');
            Log::info('Cache cleared after migrations');
        }
    });

    // Hook for specific migration
    MigrationsHook::forMigration('create_users_table', function ($data) {
        Log::info("Users table migration completed: {$data['file_name']}");
        // Seed default roles
        Artisan::call('db:seed', ['--class' => 'RoleSeeder']);
    }, 'after');

    // Before each migration
    MigrationsHook::beforeMigration(function ($data) {
        Log::info("Starting migration: {$data['file_name']}");
    });

    // Only for forward migrations (not rollbacks)
    MigrationsHook::afterMigrations(
        MigrationsHook::onlyForward(function ($data) {
            Artisan::call('db:seed');
        })
    );
}
```

### Laravel Events

Listen to migration events directly:

```php
use Illuminate\Support\Facades\Event;

Event::listen('migration_hooks.migration.ended', function ($data) {
    if (str_contains($data['file_name'], 'add_search_index')) {
        // Rebuild search index after this specific migration
        dispatch(new RebuildSearchIndexJob());
    }
});
```

## Available Hook Events

- `migrations.started` - Before any migrations run
- `migrations.ended` - After all migrations complete
- `migration.started` - Before each individual migration
- `migration.ended` - After each individual migration

Each event receives data:
```php
[
    'method' => 'up|down',           // Migration direction
    'connection' => 'mysql',         // Database connection
    'migration' => $migrationClass,  // Migration instance
    'file_path' => '/full/path.php', // Full file path
    'file_name' => 'migration_name'  // Just the filename
]
```

## Management Commands

### List Migration Hooks

```bash
# Show all migrations and their hook status
php artisan migration-hooks:list

# Show only migrations without hooks
php artisan migration-hooks:list --missing
```

### Create Hook Files

```bash
php artisan make:migration-hook create_users_table
```

## Advanced Usage

### Environment-specific Hooks

```php
MigrationsHook::beforeMigrations(function ($data) {
    if (app()->environment('production')) {
        Log::warning('Running migrations in production - ensure backup exists');
        
        // You could even halt migration in production
        if (!config('app.production_migrations_allowed')) {
            throw new Exception('Production migrations disabled');
        }
    }
});
```

### Database Optimization

```php
MigrationsHook::forMigration('create_large_table', function ($data) {
    // Optimize large tables after creation
    DB::statement('ANALYZE TABLE large_table');
    DB::statement('OPTIMIZE TABLE large_table');
}, 'after');
```

### Cache Management

```php
MigrationsHook::afterMigrations(
    MigrationsHook::onlyForward(function () {
        // Clear all caches after successful migrations
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
    })
);
```

### Search Index Updates

```php
MigrationsHook::afterMigration(function ($data) {
    // Update search indices after any table modification
    $searchableTables = ['users', 'posts', 'products'];
    
    foreach ($searchableTables as $table) {
        if (str_contains($data['file_name'], $table)) {
            dispatch(new UpdateSearchIndexJob($table));
            break;
        }
    }
});
```

## Configuration

### Environment Variables

```env
MIGRATION_HOOKS_ENABLED=true
MIGRATION_HOOKS_PATH=/custom/hooks/path
MIGRATION_HOOKS_HALT_ON_ERROR=false
MIGRATION_HOOKS_STRICT_MODE=false
MIGRATION_HOOKS_LOG_EXECUTION=true
MIGRATION_HOOKS_TIMEOUT=60
```

### Configuration Options

- **enabled**: Enable/disable all hooks globally
- **path**: Directory where hook files are stored
- **halt_on_error**: Stop migration if hook fails
- **strict_mode**: Require hook files to return objects
- **log_execution**: Log hook execution with timing
- **timeout**: Maximum hook execution time (seconds)

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Adinnu Benedict](https://github.com/benqoder)
- [All Contributors](../../contributors)
- This package was partly developed with assistance from VSCode Copilot

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
