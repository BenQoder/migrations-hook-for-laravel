<?php

use BenQoder\MigrationsHook\MigrationsHook;
use BenQoder\MigrationsHook\MigrationsHookServiceProvider;
use Illuminate\Database\Migrations\Migration;

/**
 * Comprehensive test suite for Migration Hooks core functionality
 */
describe('Hook Registration and Management', function () {
    beforeEach(function () {
        MigrationsHook::clearHooks();
    });

    it('can register and retrieve basic hooks', function () {
        $called = false;
        MigrationsHook::afterMigrations(function () use (&$called) {
            $called = true;
        });

        $hooks = MigrationsHook::getHooks('migrations.ended');
        expect($hooks)->toHaveCount(1);
        expect($hooks[0])->toHaveKeys(['callback', 'priority']);

        call_user_func($hooks[0]['callback'], []);
        expect($called)->toBeTrue();
    });

    it('handles hook priorities correctly', function () {
        $order = [];

        MigrationsHook::afterMigrations(function () use (&$order) {
            $order[] = 'second';
        }, 20);

        MigrationsHook::afterMigrations(function () use (&$order) {
            $order[] = 'first';
        }, 10);

        MigrationsHook::afterMigrations(function () use (&$order) {
            $order[] = 'third';
        }, 30);

        $hooks = MigrationsHook::getHooks('migrations.ended');
        foreach ($hooks as $hook) {
            call_user_func($hook['callback'], []);
        }

        expect($order)->toBe(['first', 'second', 'third']);
    });

    it('can filter hooks for forward migrations only', function () {
        $called = false;

        $forwardOnlyCallback = MigrationsHook::onlyForward(function () use (&$called) {
            $called = true;
        });

        // Test with up migration
        call_user_func($forwardOnlyCallback, ['method' => 'up']);
        expect($called)->toBeTrue();

        // Reset and test with down migration
        $called = false;
        call_user_func($forwardOnlyCallback, ['method' => 'down']);
        expect($called)->toBeFalse();
    });

    it('can filter hooks for rollback migrations only', function () {
        $called = false;

        $rollbackOnlyCallback = MigrationsHook::onlyRollback(function () use (&$called) {
            $called = true;
        });

        // Test with down migration
        call_user_func($rollbackOnlyCallback, ['method' => 'down']);
        expect($called)->toBeTrue();

        // Reset and test with up migration
        $called = false;
        call_user_func($rollbackOnlyCallback, ['method' => 'up']);
        expect($called)->toBeFalse();
    });

    it('can register hooks for specific migrations', function () {
        $called = false;
        MigrationsHook::forMigration('create_users_table', function () use (&$called) {
            $called = true;
        });

        $hooks = MigrationsHook::getHooks('migration.ended');
        expect($hooks)->toHaveCount(1);

        // Test with matching migration
        call_user_func($hooks[0]['callback'], ['file_name' => 'create_users_table']);
        expect($called)->toBeTrue();

        // Test with non-matching migration
        $called = false;
        call_user_func($hooks[0]['callback'], ['file_name' => 'create_posts_table']);
        expect($called)->toBeFalse();
    });
});

describe('Service Provider Integration', function () {
    it('handles migration events without errors', function () {
        config(['migrations-hook-for-laravel.enabled' => true]);

        $provider = new MigrationsHookServiceProvider($this->app);

        // Test MigrationsStarted event
        $event = new \Illuminate\Database\Events\MigrationsStarted('up');
        $result = $provider->handleMigrationsStarted($event);
        expect($result)->toBeNull();

        // Test MigrationsEnded event
        $event = new \Illuminate\Database\Events\MigrationsEnded('up');
        $result = $provider->handleMigrationsEnded($event);
        expect($result)->toBeNull();
    });

    it('extracts migration file information correctly', function () {
        $provider = new MigrationsHookServiceProvider($this->app);

        $migration = new class extends Migration
        {
            public function up() {}

            public function down() {}
        };

        $reflection = new ReflectionClass($provider);

        $getFilePathMethod = $reflection->getMethod('getMigrationFilePath');
        $getFilePathMethod->setAccessible(true);

        $getFileNameMethod = $reflection->getMethod('getMigrationFileName');
        $getFileNameMethod->setAccessible(true);

        $filePath = $getFilePathMethod->invoke($provider, $migration);
        expect($filePath)->toBeString();

        $fileName = $getFileNameMethod->invoke($provider, $filePath);
        expect($fileName)->toBeString();
        expect($fileName)->not->toContain('.php');

        // Test with a realistic migration filename
        $customFileName = $getFileNameMethod->invoke($provider, '/database/migrations/2024_01_01_000000_create_users_table.php');
        expect($customFileName)->toBe('2024_01_01_000000_create_users_table');
    });

    it('respects configuration settings', function () {
        // Test with hooks disabled
        config(['migrations-hook-for-laravel.enabled' => false]);

        $provider = new MigrationsHookServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);

        $executeFileHookMethod = $reflection->getMethod('executeFileHook');
        $executeFileHookMethod->setAccessible(true);

        // Should return early when hooks are disabled
        $result = $executeFileHookMethod->invoke($provider, 'test_migration', 'up', 'after');
        expect($result)->toBeNull();

        // Test with hooks enabled but missing file
        config(['migrations-hook-for-laravel.enabled' => true]);
        config(['migrations-hook-for-laravel.path' => '/nonexistent/path']);

        // Should handle missing hook file gracefully
        $result = $executeFileHookMethod->invoke($provider, 'test_migration', 'up', 'after');
        expect($result)->toBeNull();
    });
});

describe('File-based Hook Execution', function () {
    it('can find and execute hook files', function () {
        $hooksPath = database_path('hooks');
        if (! is_dir($hooksPath)) {
            mkdir($hooksPath, 0755, true);
        }

        $testHookFile = $hooksPath.'/test_file_execution.php';

        $hookContent = <<<'PHP'
<?php

return new class {
    public function beforeUp()
    {
        touch(storage_path('test_beforeUp.flag'));
    }
    
    public function afterUp()
    {
        touch(storage_path('test_afterUp.flag'));
    }
};
PHP;

        file_put_contents($testHookFile, $hookContent);

        config(['migrations-hook-for-laravel.enabled' => true]);
        config(['migrations-hook-for-laravel.path' => $hooksPath]);

        $provider = new MigrationsHookServiceProvider($this->app);

        $reflection = new ReflectionClass($provider);
        $executeFileHookMethod = $reflection->getMethod('executeFileHook');
        $executeFileHookMethod->setAccessible(true);

        // Clean up any existing flag files
        @unlink(storage_path('test_beforeUp.flag'));
        @unlink(storage_path('test_afterUp.flag'));

        // Execute beforeUp hook
        $executeFileHookMethod->invoke($provider, 'test_file_execution', 'up', 'before');
        expect(file_exists(storage_path('test_beforeUp.flag')))->toBeTrue();

        // Execute afterUp hook
        $executeFileHookMethod->invoke($provider, 'test_file_execution', 'up', 'after');
        expect(file_exists(storage_path('test_afterUp.flag')))->toBeTrue();

        // Cleanup
        unlink($testHookFile);
        @unlink(storage_path('test_beforeUp.flag'));
        @unlink(storage_path('test_afterUp.flag'));
    });

    it('validates hook file structure', function () {
        $hooksPath = database_path('hooks');
        if (! is_dir($hooksPath)) {
            mkdir($hooksPath, 0755, true);
        }

        $testHookFile = $hooksPath.'/structure_test.php';

        $hookContent = <<<'PHP'
<?php

return new class {
    public function beforeUp() {}
    public function afterUp() {}
    public function beforeDown() {}
    public function afterDown() {}
};
PHP;

        file_put_contents($testHookFile, $hookContent);

        // Test that we can load the hook file and it has expected methods
        $hookInstance = include $testHookFile;

        expect($hookInstance)->toBeObject();
        expect(method_exists($hookInstance, 'beforeUp'))->toBeTrue();
        expect(method_exists($hookInstance, 'afterUp'))->toBeTrue();
        expect(method_exists($hookInstance, 'beforeDown'))->toBeTrue();
        expect(method_exists($hookInstance, 'afterDown'))->toBeTrue();

        // Cleanup
        unlink($testHookFile);
    });
});
