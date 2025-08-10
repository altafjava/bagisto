<?php

use Webkul\Core\Facades\Acl;
use Webkul\Core\Facades\Core;
use Webkul\Core\Facades\Menu;
use Webkul\Core\Facades\SystemConfig;

if (! function_exists('core')) {
    /**
     * Core helper.
     *
     * @return \Webkul\Core\Core
     */
    function core()
    {
        return Core::getFacadeRoot();
    }
}

if (! function_exists('menu')) {
    /**
     * Menu helper.
     *
     * @return \Webkul\Core\Menu
     */
    function menu()
    {
        return Menu::getFacadeRoot();
    }
}

if (! function_exists('acl')) {
    /**
     * Acl helper.
     *
     * @return \Webkul\Core\Acl
     */
    function acl()
    {
        return Acl::getFacadeRoot();
    }
}

if (! function_exists('system_config')) {
    /**
     * System Config helper.
     *
     * @return \Webkul\Core\SystemConfig
     */
    function system_config()
    {
        return SystemConfig::getFacadeRoot();
    }
}

if (! function_exists('clean_path')) {
    /**
     * Clean path.
     */
    function clean_path(string $path): string
    {
        return collect(explode('/', $path))
            ->filter(fn ($segment) => ! empty($segment))
            ->join('/');
    }
}

if (! function_exists('array_permutation')) {
    function array_permutation($input)
    {
        $results = [];

        foreach ($input as $key => $values) {
            if (empty($values)) {
                continue;
            }

            if (empty($results)) {
                foreach ($values as $value) {
                    $results[] = [$key => $value];
                }
            } else {
                $append = [];

                foreach ($results as &$result) {
                    $result[$key] = array_shift($values);

                    $copy = $result;

                    foreach ($values as $item) {
                        $copy[$key] = $item;
                        $append[] = $copy;
                    }

                    array_unshift($values, $result[$key]);
                }

                $results = array_merge($results, $append);
            }
        }

        return $results;
    }
}

if (! function_exists('intelligent_asset_url')) {
    /**
     * Generate an asset URL using intelligent resolution.
     * 
     * This function provides intelligent URL handling for both Cloudinary and local storage.
     * It's specifically designed for uploaded media files (products, categories, etc.).
     *
     * @param  string|null  $path
     * @return string|null
     */
    function intelligent_asset_url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // For uploaded media files (products, categories, etc.), use intelligent resolution
        return app(\Webkul\Core\Services\AssetUrlResolver::class)->resolve($path);
    }
}

if (! function_exists('intelligent_storage_url')) {
    /**
     * Get the URL for a file using intelligent storage resolution.
     * 
     * This function serves as a drop-in replacement for Storage::url() calls
     * and provides intelligent URL handling for both Cloudinary and local storage.
     *
     * @param  string|null  $path
     * @param  string|null  $disk
     * @return string|null
     */
    function intelligent_storage_url(?string $path, ?string $disk = null): ?string
    {
        if (empty($path)) {
            return null;
        }

        return app(\Webkul\Core\Services\IntelligentStorageManager::class)->url($path, $disk);
    }
}

if (! function_exists('smart_storage_url')) {
    /**
     * Get the URL for a file using the intelligent storage facade.
     * 
     * This function provides the most advanced URL resolution with automatic
     * fallback and error handling for both Cloudinary and local storage.
     *
     * @param  string|null  $path
     * @param  string|null  $disk
     * @return string|null
     */
    function smart_storage_url(?string $path, ?string $disk = null): ?string
    {
        if (empty($path)) {
            return null;
        }

        return app('intelligent-storage')->url($path, $disk);
    }
}
