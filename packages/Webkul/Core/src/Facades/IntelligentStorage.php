<?php

namespace Webkul\Core\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Intelligent Storage Facade
 * 
 * This facade provides intelligent URL resolution for storage operations,
 * automatically handling Cloudinary and local storage URLs without
 * requiring changes to existing code.
 * 
 * @method static string|null url(?string $path, ?string $disk = null)
 * @method static array urls(array $paths, ?string $disk = null)
 * @method static bool isCompleteUrl(string $path)
 * @method static bool isCloudinaryUrl(string $path)
 * @method static bool isLocalPath(string $path)
 * 
 * @see \Webkul\Core\Services\IntelligentStorageFacade
 */
class IntelligentStorage extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'intelligent-storage';
    }
}