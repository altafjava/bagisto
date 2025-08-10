<?php

namespace Webkul\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Webkul\Core\Services\StorageService;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(StorageService::class, function ($app) {
            return new StorageService();
        });

        $this->app->alias(StorageService::class, 'storage.service');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../Config/bagisto-cloudinary.php' => config_path('bagisto-cloudinary.php'),
        ], 'bagisto-cloudinary-config');

        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/bagisto-cloudinary.php',
            'bagisto-cloudinary'
        );
    }
}