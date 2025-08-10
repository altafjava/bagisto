<?php

namespace Webkul\Core\Services;

use Illuminate\Support\Facades\Storage;
use Webkul\Core\Services\AssetUrlResolver;

/**
 * Intelligent Storage Manager
 * 
 * This service provides a centralized way to handle storage operations
 * with intelligent URL resolution for both Cloudinary and local storage.
 * 
 * Instead of modifying core files, this service can be used as a drop-in
 * replacement for Storage::url() calls where intelligent URL handling is needed.
 */
class IntelligentStorageManager
{
    /**
     * Asset URL resolver instance.
     *
     * @var AssetUrlResolver
     */
    protected $assetUrlResolver;

    /**
     * Create a new intelligent storage manager instance.
     *
     * @param  AssetUrlResolver  $assetUrlResolver
     * @return void
     */
    public function __construct(AssetUrlResolver $assetUrlResolver)
    {
        $this->assetUrlResolver = $assetUrlResolver;
    }

    /**
     * Get the URL for the file at the given path with intelligent resolution.
     * 
     * This method provides intelligent URL handling:
     * - For Cloudinary URLs: Returns them as-is
     * - For local paths: Uses Laravel's Storage::url()
     * - Handles null/empty paths gracefully
     *
     * @param  string|null  $path
     * @param  string|null  $disk
     * @return string|null
     */
    public function url(?string $path, ?string $disk = null): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Use the intelligent_asset_url helper for intelligent URL resolution
        $resolvedUrl = intelligent_asset_url($path);
        
        if ($resolvedUrl !== null) {
            return $resolvedUrl;
        }

        // Fallback to Laravel's default Storage behavior
        return $disk ? Storage::disk($disk)->url($path) : Storage::url($path);
    }

    /**
     * Get URLs for multiple paths.
     *
     * @param  array  $paths
     * @param  string|null  $disk
     * @return array
     */
    public function urls(array $paths, ?string $disk = null): array
    {
        return array_map(function ($path) use ($disk) {
            return $this->url($path, $disk);
        }, $paths);
    }

    /**
     * Check if a file exists.
     *
     * @param  string  $path
     * @param  string|null  $disk
     * @return bool
     */
    public function exists(string $path, ?string $disk = null): bool
    {
        return $disk ? Storage::disk($disk)->exists($path) : Storage::exists($path);
    }

    /**
     * Get the contents of a file.
     *
     * @param  string  $path
     * @param  string|null  $disk
     * @return string
     */
    public function get(string $path, ?string $disk = null): string
    {
        return $disk ? Storage::disk($disk)->get($path) : Storage::get($path);
    }

    /**
     * Store a file.
     *
     * @param  string  $path
     * @param  mixed  $contents
     * @param  array  $options
     * @param  string|null  $disk
     * @return bool
     */
    public function put(string $path, $contents, array $options = [], ?string $disk = null): bool
    {
        return $disk 
            ? Storage::disk($disk)->put($path, $contents, $options)
            : Storage::put($path, $contents, $options);
    }

    /**
     * Delete a file or files.
     *
     * @param  string|array  $paths
     * @param  string|null  $disk
     * @return bool
     */
    public function delete($paths, ?string $disk = null): bool
    {
        return $disk ? Storage::disk($disk)->delete($paths) : Storage::delete($paths);
    }

    /**
     * Get all files in a directory.
     *
     * @param  string|null  $directory
     * @param  string|null  $disk
     * @return array
     */
    public function files(?string $directory = null, ?string $disk = null): array
    {
        return $disk ? Storage::disk($disk)->files($directory) : Storage::files($directory);
    }

    /**
     * Get all directories in a directory.
     *
     * @param  string|null  $directory
     * @param  string|null  $disk
     * @return array
     */
    public function directories(?string $directory = null, ?string $disk = null): array
    {
        return $disk ? Storage::disk($disk)->directories($directory) : Storage::directories($directory);
    }
}