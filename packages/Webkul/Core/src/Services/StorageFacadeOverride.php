<?php

namespace Webkul\Core\Services;

use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Webkul\Core\Services\AssetUrlResolver;
use Webkul\Core\Services\IntelligentStorageFacade;

/**
 * Storage Facade Override Manager
 * 
 * This class provides a mechanism to override the Storage facade's url() method
 * without modifying core Laravel or Bagisto classes. It uses Laravel's service
 * container to replace the Storage facade with an intelligent version.
 */
class StorageFacadeOverride extends FilesystemManager
{
    /**
     * The intelligent storage facade instance.
     */
    protected IntelligentStorageFacade $intelligentStorage;

    /**
     * Create a new storage facade override instance.
     */
    public function __construct($app, IntelligentStorageFacade $intelligentStorage)
    {
        parent::__construct($app);
        $this->intelligentStorage = $intelligentStorage;
    }

    /**
     * Get the URL for a file with intelligent resolution.
     * 
     * This method overrides the default Storage::url() behavior to provide
     * intelligent URL handling for both Cloudinary and local storage.
     */
    public function url($path)
    {
        return $this->intelligentStorage->url($path);
    }

    /**
     * Get the URL for a file on a specific disk with intelligent resolution.
     */
    public function disk($name = null)
    {
        $disk = parent::disk($name);
        
        // Create a wrapper that overrides the url method
        return new class($disk, $this->intelligentStorage) {
            protected $disk;
            protected $intelligentStorage;

            public function __construct($disk, $intelligentStorage)
            {
                $this->disk = $disk;
                $this->intelligentStorage = $intelligentStorage;
            }

            public function url($path)
            {
                return $this->intelligentStorage->url($path);
            }

            public function __call($method, $arguments)
            {
                return $this->disk->$method(...$arguments);
            }
        };
    }
}