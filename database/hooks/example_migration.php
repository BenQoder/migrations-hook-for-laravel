<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Hook for: example_migration
 *
 * This hook will be automatically executed when the migration runs.
 * Available methods: beforeUp, afterUp, beforeDown, afterDown
 */
return new class
{
    /**
     * Execute before migration up
     */
    public function beforeUp()
    {
        Log::info('Before migration up: example_migration');

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
        Log::info('After migration up: example_migration');

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
        Log::info('Before migration down: example_migration');

        // Your code here - runs BEFORE rollback
        // Example: Backup data that will be lost

        // Example: Backup important data before rollback
        // $importantData = DB::table('users')->where('role', 'admin')->get();
        // Cache::put('admin_backup_example_migration', $importantData, 3600);
    }

    /**
     * Execute after migration down (rollback)
     */
    public function afterDown()
    {
        Log::info('After migration down: example_migration');

        // Your code here - runs AFTER rollback
        // Example: Cleanup, restore backups, etc.

        // Example: Restore backed up data
        // $backup = Cache::get('admin_backup_example_migration');
        // if ($backup) {
        //     // Restore logic here
        //     Cache::forget('admin_backup_example_migration');
        // }
    }
};
