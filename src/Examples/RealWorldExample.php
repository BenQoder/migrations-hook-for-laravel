<?php

namespace BenQoder\MigrationsHook\Examples;

use BenQoder\MigrationsHook\Facades\MigrationsHook;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Complete Usage Examples for Migration Hooks
 *
 * This file demonstrates all the ways to use Migration Hooks in your Laravel application.
 * Copy the methods you need into your AppServiceProvider's boot() method.
 */
class ComprehensiveExample extends ServiceProvider
{
    public function boot()
    {
        // Choose which examples to enable:
        $this->basicHookExamples();
        $this->environmentSpecificHooks();
        $this->productionSafetyHooks();
        $this->performanceOptimizationHooks();
        $this->eventBasedHooks();
    }

    /**
     * Basic hook registration examples
     */
    protected function basicHookExamples()
    {
        // 1. Clear cache after all migrations
        MigrationsHook::afterMigrations(function ($data) {
            if ($data['method'] === 'up') {
                Artisan::call('cache:clear');
                Log::info('Cache cleared after migrations');
            }
        });

        // 2. Hook for specific migration
        MigrationsHook::forMigration('create_users_table', function ($data) {
            // Seed admin user after users table is created
            \DB::table('users')->updateOrInsert(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Administrator',
                    'email' => 'admin@example.com',
                    'password' => \Hash::make('password'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }, 'after');

        // 3. Before each migration logging
        MigrationsHook::beforeMigration(function ($data) {
            Log::info("Starting migration: {$data['file_name']}");
        });

        // 4. Only for forward migrations (not rollbacks)
        MigrationsHook::afterMigrations(
            MigrationsHook::onlyForward(function ($data) {
                Artisan::call('db:seed');
            })
        );
    }

    /**
     * Environment-specific hooks
     */
    protected function environmentSpecificHooks()
    {
        // Development environment hooks
        if (app()->environment('local', 'development')) {
            MigrationsHook::afterMigrations(
                MigrationsHook::onlyForward(function ($data) {
                    Log::info('Running development seeders');
                    Artisan::call('db:seed', ['--class' => 'DevelopmentSeeder']);
                })
            );
        }

        // Production environment hooks
        if (app()->environment('production')) {
            MigrationsHook::afterMigrations(
                MigrationsHook::onlyForward(function ($data) {
                    // Clear and warm up caches in production
                    Artisan::call('cache:clear');
                    Artisan::call('config:cache');
                    Artisan::call('route:cache');
                    Artisan::call('view:cache');
                })
            );
        }
    }

    /**
     * Production safety and monitoring hooks
     */
    protected function productionSafetyHooks()
    {
        // Safety check before migrations
        MigrationsHook::beforeMigrations(function ($data) {
            if (app()->environment('production')) {
                Log::warning('Production migration started', [
                    'method' => $data['method'],
                    'timestamp' => now(),
                ]);

                // Add safety checks:
                // - Verify backup exists
                // - Check maintenance mode
                // - Validate disk space
            }
        });

        // Notification when migrations complete
        MigrationsHook::afterMigrations(function ($data) {
            if ($data['method'] === 'up' && app()->environment('production')) {
                Log::info('All production migrations completed successfully');
                // Send Slack notification, email, etc.
            }
        });
    }

    /**
     * Performance optimization hooks
     */
    protected function performanceOptimizationHooks()
    {
        // Optimize large tables after creation
        MigrationsHook::forMigration('analytics', function ($data) {
            if (str_contains($data['file_name'], 'analytics')) {
                Log::info('Optimizing analytics table');
                \DB::statement('ANALYZE TABLE analytics_events');
                \DB::statement('OPTIMIZE TABLE analytics_events');
            }
        }, 'after');

        // Update search indices after content changes
        MigrationsHook::afterMigration(function ($data) {
            $searchableTables = ['users', 'posts', 'products'];

            foreach ($searchableTables as $table) {
                if (str_contains($data['file_name'], $table)) {
                    Log::info("Updating search index for {$table}");
                    // Dispatch search index update job
                    if (class_exists('\App\Jobs\UpdateSearchIndexJob')) {
                        dispatch(new \App\Jobs\UpdateSearchIndexJob($table));
                    }
                    break;
                }
            }
        });
    }

    /**
     * Event-based hooks for complex scenarios
     */
    protected function eventBasedHooks()
    {
        // Listen to custom migration events
        Event::listen('migration_hooks.migration.ended', function ($data) {
            $fileName = $data['file_name'] ?? '';

            // Handle different migration types
            match (true) {
                str_contains($fileName, 'create_') && str_contains($fileName, '_table') => $this->handleTableCreation($data),
                str_contains($fileName, 'add_index') => $this->handleIndexAddition($data),
                str_contains($fileName, 'drop_') => $this->handleTableDrop($data),
                default => null
            };
        });
    }

    /**
     * Handle table creation events
     */
    protected function handleTableCreation(array $data): void
    {
        Log::info("New table created: {$data['file_name']}");
        // Update documentation, notify team, update monitoring
    }

    /**
     * Handle index addition events
     */
    protected function handleIndexAddition(array $data): void
    {
        Log::info("Database index added: {$data['file_name']}");
        // Update performance monitoring, restart query cache
    }

    /**
     * Handle table drop events
     */
    protected function handleTableDrop(array $data): void
    {
        Log::warning("Table dropped: {$data['file_name']}");
        // Alert administrators, clean up configurations
    }
}
