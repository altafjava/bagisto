<?php

namespace Webkul\Core\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Cloudinary\Cloudinary;

class StorageService
{
    /**
     * Get the configured storage disk.
     *
     * @return string
     */
    public function getStorageDisk(): string
    {
        $cloudinaryEnabled = config('bagisto-cloudinary.enabled', false);
        $cloudinaryConfigured = $this->isCloudinaryConfigured();

        if ($cloudinaryEnabled && $cloudinaryConfigured) {
            return 'cloudinary';
        }

        return config('filesystems.default', 'public');
    }

    /**
     * Get the configured storage disk (alias for getStorageDisk).
     *
     * @return string
     */
    public function getDisk(): string
    {
        return $this->getStorageDisk();
    }

    /**
     * Check if Cloudinary is properly configured.
     *
     * @return bool
     */
    public function isCloudinaryConfigured(): bool
    {
        try {
            $cloudName = config('cloudinary.cloud_name');
            $apiKey = config('cloudinary.api_key');
            $apiSecret = config('cloudinary.api_secret');

            if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
                return false;
            }

            // Test the configuration by creating a Cloudinary instance
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                    'secure' => config('cloudinary.secure_url', true),
                ]
            ]);

            // If we can create the instance without exception, configuration is valid
            return true;
        } catch (Exception $e) {
            logger()->warning('Cloudinary configuration check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Initialize Cloudinary instance.
     *
     * @return \Cloudinary\Cloudinary
     * @throws \Exception
     */
    private function initializeCloudinary(): Cloudinary
    {
        try {
            $cloudName = config('cloudinary.cloud_name');
            $apiKey = config('cloudinary.api_key');
            $apiSecret = config('cloudinary.api_secret');

            if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
                throw new Exception('Cloudinary configuration is incomplete. Please check your environment variables.');
            }

            return new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                    'secure' => config('cloudinary.secure_url', true),
                ]
            ]);
        } catch (Exception $e) {
            logger()->error('Failed to initialize Cloudinary', [
                'error' => $e->getMessage(),
                'cloud_name' => config('cloudinary.cloud_name', 'not_set'),
                'api_key_set' => !empty(config('cloudinary.api_key')),
                'api_secret_set' => !empty(config('cloudinary.api_secret')),
            ]);
            throw $e;
        }
    }

    /**
     * Prepare upload options with signature support.
     *
     * @param  array  $baseOptions
     * @param  array  $userOptions
     * @return array
     */
    private function prepareUploadOptions(array $baseOptions, array $userOptions = []): array
    {
        $uploadOptions = array_merge($baseOptions, $userOptions);

        // Add upload_preset if configured
        if (config('cloudinary.upload_preset')) {
            $uploadOptions['upload_preset'] = config('cloudinary.upload_preset');
        }

        // Add notification_url if configured
        if (config('cloudinary.notification_url')) {
            $uploadOptions['notification_url'] = config('cloudinary.notification_url');
        }

        // For signed uploads, we need to ensure the signature is generated
        // The Cloudinary SDK will automatically handle signature generation
        // when api_secret is provided and the upload is not using unsigned presets
        
        return $uploadOptions;
    }

    /**
     * Upload an image file.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $folder
     * @param  array  $options
     * @return string
     */
    public function uploadImage(UploadedFile $file, string $folder, array $options = []): string
    {
        $disk = $this->getStorageDisk();

        if ($disk === 'cloudinary') {
            try {
                return $this->uploadImageToCloudinary($file, $folder, $options);
            } catch (Exception $e) {
                logger()->warning('Cloudinary image upload failed, falling back to local storage', [
                    'error' => $e->getMessage(),
                    'file' => $file->getClientOriginalName(),
                    'folder' => $folder
                ]);
                
                // Fallback to local storage
                return $this->uploadImageToLocal($file, $folder, $options);
            }
        }

        return $this->uploadImageToLocal($file, $folder, $options);
    }

    /**
     * Upload a video file.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $folder
     * @param  array  $options
     * @return string
     */
    public function uploadVideo(UploadedFile $file, string $folder, array $options = []): string
    {
        $disk = $this->getStorageDisk();

        if ($disk === 'cloudinary') {
            try {
                return $this->uploadVideoToCloudinary($file, $folder, $options);
            } catch (Exception $e) {
                logger()->warning('Cloudinary video upload failed, falling back to local storage', [
                    'error' => $e->getMessage(),
                    'file' => $file->getClientOriginalName(),
                    'folder' => $folder
                ]);
                
                // Fallback to local storage
                return $this->uploadVideoToLocal($file, $folder, $options);
            }
        }

        return $this->uploadVideoToLocal($file, $folder, $options);
    }

    /**
     * Upload any file.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $folder
     * @param  array  $options
     * @return string
     */
    public function uploadFile(UploadedFile $file, string $folder, array $options = []): string
    {
        if (Str::contains($file->getMimeType(), 'image')) {
            return $this->uploadImage($file, $folder, $options);
        }

        if (Str::contains($file->getMimeType(), 'video')) {
            return $this->uploadVideo($file, $folder, $options);
        }

        // For other file types, use local storage
        return $this->uploadFileToLocal($file, $folder, $options);
    }

    /**
     * Delete a file.
     *
     * @param  string  $path
     * @return bool
     */
    public function deleteFile(string $path): bool
    {
        try {
            // Try to delete from Cloudinary first if it looks like a Cloudinary URL
            if ($this->isCloudinaryUrl($path)) {
                return $this->deleteFromCloudinary($path);
            }

            // Otherwise delete from local storage
            return Storage::delete($path);
        } catch (Exception $e) {
            // Log error but don't throw to prevent breaking the application
            logger()->error('Failed to delete file: ' . $path, ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get file URL.
     *
     * @param  string  $path
     * @return string
     */
    public function getFileUrl(string $path): string
    {
        if ($this->isCloudinaryUrl($path)) {
            return $path; // Cloudinary URLs are already complete
        }

        // Use AssetUrlResolver to avoid circular dependency with Storage facade override
        $assetUrlResolver = app(\Webkul\Core\Services\AssetUrlResolver::class);
        return $assetUrlResolver->resolve($path);
    }

    /**
     * Upload image to Cloudinary.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $folder
     * @param  array  $options
     * @return string
     */
    private function uploadImageToCloudinary(UploadedFile $file, string $folder, array $options = []): string
    {
        try {
            // Initialize Cloudinary
            $cloudinary = $this->initializeCloudinary();

            // Process image with Intervention Image if needed
            $manager = new ImageManager;
            $image = $manager->make($file);

            // Convert to WebP for better compression
            if (!isset($options['format'])) {
                $image = $image->encode('webp');
            }

            // Create a temporary file for Cloudinary upload
            $tempPath = tempnam(sys_get_temp_dir(), 'bagisto_upload_');
            file_put_contents($tempPath, $image);

            $baseOptions = [
                'folder' => $folder,
                'resource_type' => 'image',
                'format' => $options['format'] ?? 'webp',
                'quality' => $options['quality'] ?? 'auto',
                'fetch_format' => 'auto',
            ];

            $uploadOptions = $this->prepareUploadOptions($baseOptions, $options);

            $result = $cloudinary->uploadApi()->upload($tempPath, $uploadOptions);

            // Clean up temporary file
            unlink($tempPath);

            return $result['secure_url'];
        } catch (Exception $e) {
            // Fallback to local storage if Cloudinary fails
            logger()->warning('Cloudinary image upload failed, falling back to local storage', [
                'error' => $e->getMessage(),
                'folder' => $folder
            ]);

            return $this->uploadImageToLocal($file, $folder, $options);
        }
    }

    /**
     * Upload video to Cloudinary.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $folder
     * @param  array  $options
     * @return string
     */
    private function uploadVideoToCloudinary(UploadedFile $file, string $folder, array $options = []): string
    {
        try {
            // Initialize Cloudinary
            $cloudinary = $this->initializeCloudinary();

            $baseOptions = [
                'folder' => $folder,
                'resource_type' => 'video',
                'quality' => $options['quality'] ?? 'auto',
            ];

            $uploadOptions = $this->prepareUploadOptions($baseOptions, $options);

            $result = $cloudinary->uploadApi()->upload($file->getRealPath(), $uploadOptions);

            return $result['secure_url'];
        } catch (Exception $e) {
            // Fallback to local storage if Cloudinary fails
            logger()->warning('Cloudinary video upload failed, falling back to local storage', [
                'error' => $e->getMessage(),
                'folder' => $folder
            ]);

            return $this->uploadVideoToLocal($file, $folder, $options);
        }
    }

    /**
     * Upload image to local storage.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $folder
     * @param  array  $options
     * @return string
     */
    private function uploadImageToLocal(UploadedFile $file, string $folder, array $options = []): string
    {
        $manager = new ImageManager;
        $image = $manager->make($file)->encode('webp');
        $path = $folder . '/' . Str::random(40) . '.webp';

        Storage::put($path, $image);

        return $path;
    }

    /**
     * Upload video to local storage.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $folder
     * @param  array  $options
     * @return string
     */
    private function uploadVideoToLocal(UploadedFile $file, string $folder, array $options = []): string
    {
        return $file->store($folder);
    }

    /**
     * Upload file to local storage.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $folder
     * @param  array  $options
     * @return string
     */
    private function uploadFileToLocal(UploadedFile $file, string $folder, array $options = []): string
    {
        return $file->store($folder);
    }

    /**
     * Delete file from Cloudinary.
     *
     * @param  string  $url
     * @return bool
     */
    private function deleteFromCloudinary(string $url): bool
    {
        try {
            // Initialize Cloudinary
            $cloudinary = $this->initializeCloudinary();

            $publicId = $this->extractPublicIdFromUrl($url);
            if ($publicId) {
                $cloudinary->uploadApi()->destroy($publicId);
                return true;
            }
            return false;
        } catch (Exception $e) {
            logger()->error('Failed to delete from Cloudinary', ['url' => $url, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if URL is a Cloudinary URL.
     *
     * @param  string  $url
     * @return bool
     */
    public function isCloudinaryUrl(string $url): bool
    {
        return Str::contains($url, 'cloudinary.com') || Str::contains($url, 'res.cloudinary.com');
    }

    /**
     * Extract public ID from Cloudinary URL.
     *
     * @param  string  $url
     * @return string|null
     */
    private function extractPublicIdFromUrl(string $url): ?string
    {
        // Extract public ID from Cloudinary URL
        // Example: https://res.cloudinary.com/demo/image/upload/v1234567890/folder/filename.jpg
        if (preg_match('/\/v\d+\/(.+)\.[^.]+$/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}