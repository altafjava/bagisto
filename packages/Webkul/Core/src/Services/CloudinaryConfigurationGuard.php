<?php

namespace Webkul\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Webkul\Core\Services\StorageService;

/**
 * Cloudinary Configuration Guard
 * 
 * This service provides safe access to Cloudinary functionality with
 * automatic fallback to local storage when Cloudinary is misconfigured.
 */
class CloudinaryConfigurationGuard
{
    /**
     * The storage service instance.
     */
    protected StorageService $storageService;

    /**
     * Cache for configuration status to avoid repeated checks.
     */
    protected static ?bool $configurationStatus = null;

    /**
     * Create a new configuration guard instance.
     */
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Check if Cloudinary is properly configured and available.
     */
    public function isCloudinaryAvailable(): bool
    {
        if (self::$configurationStatus !== null) {
            return self::$configurationStatus;
        }

        try {
            self::$configurationStatus = $this->storageService->isCloudinaryConfigured();
            
            if (!self::$configurationStatus) {
                Log::warning('Cloudinary is not properly configured, falling back to local storage');
            }
            
            return self::$configurationStatus;
        } catch (Exception $e) {
            Log::error('Cloudinary configuration check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            self::$configurationStatus = false;
            return false;
        }
    }

    /**
     * Get the appropriate storage disk based on Cloudinary availability.
     */
    public function getRecommendedDisk(): string
    {
        if ($this->isCloudinaryAvailable() && config('bagisto-cloudinary.enabled', false)) {
            return 'cloudinary';
        }

        return 'public';
    }

    /**
     * Safely execute a Cloudinary operation with fallback.
     */
    public function safeCloudinaryOperation(callable $cloudinaryOperation, callable $fallbackOperation = null)
    {
        if (!$this->isCloudinaryAvailable()) {
            if ($fallbackOperation) {
                return $fallbackOperation();
            }
            throw new Exception('Cloudinary is not available and no fallback operation provided');
        }

        try {
            return $cloudinaryOperation();
        } catch (Exception $e) {
            Log::error('Cloudinary operation failed, attempting fallback', [
                'error' => $e->getMessage()
            ]);

            if ($fallbackOperation) {
                return $fallbackOperation();
            }

            throw $e;
        }
    }

    /**
     * Reset the configuration status cache.
     */
    public static function resetConfigurationCache(): void
    {
        self::$configurationStatus = null;
    }

    /**
     * Get configuration status for debugging.
     */
    public function getConfigurationStatus(): array
    {
        return [
            'cloudinary_enabled' => config('bagisto-cloudinary.enabled', false),
            'cloudinary_configured' => $this->isCloudinaryAvailable(),
            'cloud_name' => config('cloudinary.cloud_name', 'not_set'),
            'api_key_set' => !empty(config('cloudinary.api_key')),
            'api_secret_set' => !empty(config('cloudinary.api_secret')),
            'recommended_disk' => $this->getRecommendedDisk(),
        ];
    }
}