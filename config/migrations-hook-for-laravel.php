<?php

/**
 * Configuration file for Migration Hooks
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Migration Hooks Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether migration hooks are executed.
    | Set to false to disable all hooks globally.
    |
    */
    'enabled' => env('MIGRATION_HOOKS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Hooks Directory Path
    |--------------------------------------------------------------------------
    |
    | The directory where migration hook files are stored.
    | By default, this is database/hooks relative to your app root.
    |
    */
    'path' => env('MIGRATION_HOOKS_PATH', database_path('hooks')),

    /*
    |--------------------------------------------------------------------------
    | Halt on Error
    |--------------------------------------------------------------------------
    |
    | When set to true, migration will stop if a hook throws an exception.
    | When false, the error is logged but migration continues.
    |
    */
    'halt_on_error' => env('MIGRATION_HOOKS_HALT_ON_ERROR', false),

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | When true, hook files must return an object instance.
    | When false, invalid hook files are ignored with a warning.
    |
    */
    'strict_mode' => env('MIGRATION_HOOKS_STRICT_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Log Execution
    |--------------------------------------------------------------------------
    |
    | Whether to log hook execution with timing information.
    |
    */
    'log_execution' => env('MIGRATION_HOOKS_LOG_EXECUTION', true),

    /*
    |--------------------------------------------------------------------------
    | Execution Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time (in seconds) a hook can run before timing out.
    | Set to 0 to disable timeout.
    |
    */
    'timeout' => env('MIGRATION_HOOKS_TIMEOUT', 60),
];
