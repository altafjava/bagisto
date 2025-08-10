<?php

namespace Webkul\Core\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemManager;

/**
 * Asset URL Resolver Service
 * 
 * This service provides a centralized way to resolve asset URLs,
 * handling both Cloudinary and local storage URLs appropriately.
 * It follows the Single Responsibility Principle and provides
 * a clean abstraction for URL generation.
 */
class AssetUrlResolver
{
    /**
     * Resolve asset URL for display.
     * 
     * This method determines whether a given path is a complete URL
     * (like Cloudinary URLs) or a local storage path, and returns
     * the appropriate URL for display.
     *
     * @param  string|null  $path
     * @return string|null
     */
    public function resolve(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // If it's already a complete URL (Cloudinary, external CDN, etc.)
        if ($this->isCompleteUrl($path)) {
            return $path;
        }

        // For local storage paths, use the original filesystem manager directly
        // to avoid infinite recursion with our Storage facade override
        return $this->getOriginalStorageUrl($path);
    }

    /**
     * Get URL using the original filesystem manager to avoid recursion.
     */
    protected function getOriginalStorageUrl(string $path): string
    {
        // Create a fresh filesystem manager instance to avoid our override
        $filesystem = new FilesystemManager(app());
        
        // Use the default filesystem disk from config
        $defaultDisk = config('filesystems.default', 'public');
        return $filesystem->disk($defaultDisk)->url($path);
    }

    /**
     * Check if the given path is a complete URL.
     *
     * @param  string  $path
     * @return bool
     */
    private function isCompleteUrl(string $path): bool
    {
        return filter_var($path, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Resolve multiple asset URLs.
     *
     * @param  array  $paths
     * @return array
     */
    public function resolveMultiple(array $paths): array
    {
        return array_map([$this, 'resolve'], $paths);
    }

    /**
     * Check if a path is a Cloudinary URL.
     *
     * @param  string  $path
     * @return bool
     */
    public function isCloudinaryUrl(string $path): bool
    {
        return $this->isCompleteUrl($path) && 
               (str_contains($path, 'cloudinary.com') || str_contains($path, 'res.cloudinary.com'));
    }

    /**
     * Check if a path is a local storage path.
     *
     * @param  string  $path
     * @return bool
     */
    public function isLocalPath(string $path): bool
    {
        return !$this->isCompleteUrl($path);
    }
}