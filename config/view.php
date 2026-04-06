<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel view path has already been registered for you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        (function() {
            // Try storage first
            $path = storage_path('framework/views');

            // Create directory if it doesn't exist
            if (!is_dir($path)) {
                @mkdir($path, 0777, true);
            }

            // Test if writable
            $testFile = $path . '/.write_test_' . time();
            if (@file_put_contents($testFile, 'test') === false) {
                // Not writable, fallback to /tmp
                $path = '/tmp/laravel_views';
                if (!is_dir($path)) {
                    @mkdir($path, 0777, true);
                }
            } else {
                // Cleanup test file
                @unlink($testFile);
            }

            return $path;
        })()
    ),

];
