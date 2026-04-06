<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->ensureStorageDirectoriesExist();
    }

    /**
     * Ensure all required storage directories exist with proper permissions.
     */
    protected function ensureStorageDirectoriesExist(): void
    {
        $directories = [
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/testing'),
            storage_path('logs'),
            storage_path('app/public'),
            base_path('bootstrap/cache'),
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
        }
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }
}
