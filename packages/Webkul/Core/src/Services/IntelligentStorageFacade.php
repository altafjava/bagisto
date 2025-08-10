<?php

namespace Webkul\Core\Services;

use Illuminate\Support\Facades\Storage;
use Webkul\Core\Services\AssetUrlResolver;

/**
 * Intelligent Storage Facade
 * 
 * This class provides a drop-in replacement for Laravel's Storage facade
 * with intelligent URL resolution for Cloudinary and local storage.
 * It maintains backward compatibility while providing enhanced functionality.
 */
class IntelligentStorageFacade
{
    /**
     * The asset URL resolver instance.
     */
    protected AssetUrlResolver $assetUrlResolver;

    /**
     * The storage service instance.
     */
    protected StorageService $storageService;

    /**
     * Create a new intelligent storage facade instance.
     */
    public function __construct(AssetUrlResolver $assetUrlResolver, StorageService $storageService)
    {
        $this->assetUrlResolver = $assetUrlResolver;
        $this->storageService = $storageService;
    }

    /**
     * Get the URL for a file with intelligent resolution.
     * 
     * This method replaces Storage::url() calls and provides intelligent
     * URL handling for both Cloudinary and local storage.
     */
    public function url(?string $path, ?string $disk = null): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Use the asset URL resolver for intelligent URL generation
        return $this->assetUrlResolver->resolve($path);
    }

    /**
     * Get URLs for multiple files.
     */
    public function urls(array $paths, ?string $disk = null): array
    {
        return $this->assetUrlResolver->resolveMultiple($paths);
    }

    /**
     * Proxy all other Storage methods to the default Storage facade.
     */
    public function __call(string $method, array $arguments)
    {
        return Storage::$method(...$arguments);
    }

    /**
     * Proxy static calls to the default Storage facade.
     */
    public static function __callStatic(string $method, array $arguments)
    {
        return Storage::$method(...$arguments);
    }

    /**
     * Get the underlying Storage facade instance.
     */
    public function getStorageFacade()
    {
        return Storage::getFacadeRoot();
    }

    /**
     * Check if a path is a complete URL (Cloudinary, S3, etc.).
     */
    public function isCompleteUrl(string $path): bool
    {
        return $this->assetUrlResolver->isCompleteUrl($path);
    }

    /**
     * Check if a path is a Cloudinary URL.
     */
    public function isCloudinaryUrl(string $path): bool
    {
        return $this->assetUrlResolver->isCloudinaryUrl($path);
    }

    /**
     * Check if a path is a local storage path.
     */
    public function isLocalPath(string $path): bool
    {
        return $this->assetUrlResolver->isLocalPath($path);
    }
}