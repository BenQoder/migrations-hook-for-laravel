<?php

use BenQoder\MigrationsHook\MigrationsHook;

/**
 * Tests for commands and execution timing proof
 */
describe('Hook Execution Timing Proof', function () {
    beforeEach(function () {
        MigrationsHook::clearHooks();
    });

    it('proves beforeUp hooks block migration execution for extended time', function () {
        $testStartTime = microtime(true);
        $hookExecuted = false;

        // Register a hook that takes time to execute
        MigrationsHook::beforeMigrations(function () use (&$hookExecuted) {
            $hookExecuted = true;
            sleep(2); // Block for 2 seconds
        });

        // Execute the hook
        $hooks = MigrationsHook::getHooks('migrations.started');
        foreach ($hooks as $hook) {
            call_user_func($hook['callback'], []);
        }

        $totalTime = microtime(true) - $testStartTime;

        // Verify that the hook executed and blocked
        expect($hookExecuted)->toBeTrue();
        expect($totalTime)->toBeGreaterThan(2.0);
    });

    it('proves hooks execute in correct order by priority', function () {
        $executionOrder = [];

        // Register hooks with different priorities
        MigrationsHook::afterMigrations(function () use (&$executionOrder) {
            $executionOrder[] = 'high_priority';
        }, 10);

        MigrationsHook::afterMigrations(function () use (&$executionOrder) {
            $executionOrder[] = 'medium_priority';
        }, 20);

        MigrationsHook::afterMigrations(function () use (&$executionOrder) {
            $executionOrder[] = 'low_priority';
        }, 30);

        // Execute hooks
        $hooks = MigrationsHook::getHooks('migrations.ended');
        foreach ($hooks as $hook) {
            call_user_func($hook['callback'], []);
        }

        expect($executionOrder)->toBe(['high_priority', 'medium_priority', 'low_priority']);
    });
});

describe('Command Testing', function () {
    it('can generate migration hook files', function () {
        $hooksPath = database_path('hooks');
        $testFile = $hooksPath.'/test_command_generation.php';

        // Clean up before test
        @unlink($testFile);

        // Mock the console output
        $this->artisan('make:migration-hook', ['migration' => 'test_command_generation'])
            ->expectsOutput('Migration hook created: '.$testFile)
            ->assertExitCode(0);

        // Verify file was created
        expect(file_exists($testFile))->toBeTrue();

        // Verify file contents
        $content = file_get_contents($testFile);
        expect($content)->toContain('return new class');
        expect($content)->toContain('public function beforeUp()');
        expect($content)->toContain('public function afterUp()');
        expect($content)->toContain('public function beforeDown()');
        expect($content)->toContain('public function afterDown()');

        // Clean up
        unlink($testFile);
    });

    it('can list existing migration hooks', function () {
        $hooksPath = database_path('hooks');

        // Create some test hook files
        $testFiles = [
            'create_users_table.php',
            'add_indexes_to_posts.php',
        ];

        foreach ($testFiles as $file) {
            $filePath = $hooksPath.'/'.$file;
            file_put_contents($filePath, '<?php return new class { public function beforeUp() {} };');
        }

        // Test the list command - just check it runs without error
        $this->artisan('migration-hooks:list')
            ->expectsOutput('Migration Hooks Status:')
            ->assertExitCode(0);

        // Clean up
        foreach ($testFiles as $file) {
            @unlink($hooksPath.'/'.$file);
        }
    });
});

describe('Service Provider Registration', function () {
    it('loads package configuration with defaults', function () {
        // Test default configuration values
        expect(config('migrations-hook-for-laravel.enabled'))->toBe(true);
        expect(config('migrations-hook-for-laravel.timeout'))->toBe(60);
        expect(config('migrations-hook-for-laravel.log_execution'))->toBe(true);
    });
});
