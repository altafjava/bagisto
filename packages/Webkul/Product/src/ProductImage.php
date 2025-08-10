<?php

namespace Webkul\Product;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Webkul\Customer\Contracts\Wishlist;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Core\Services\StorageService;

class ProductImage
{
    /**
     * Create a new helper instance.
     *
     * @return void
     */
    public function __construct(
        protected ProductRepository $productRepository,
        protected StorageService $storageService
    ) {}

    /**
     * Retrieve collection of gallery images.
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return array
     */
    public function getGalleryImages($product)
    {
        if (! $product) {
            return [];
        }

        $images = [];

        foreach ($product->images as $image) {
            // Skip Storage::has() check for complete URLs (Cloudinary, S3, etc.)
            if (! $this->isCompleteUrl($image->path) && ! Storage::has($image->path)) {
                continue;
            }

            $images[] = $this->getCachedImageUrls($image->path);
        }

        if (
            ! $product->parent_id
            && ! count($images)
            && ! count($product->videos ?? [])
        ) {
            $images[] = $this->getFallbackImageUrls();
        }

        /*
         * Product parent checked already above. If the case reached here that means the
         * parent is available. So recursing the method for getting the parent image if
         * images of the child are not found.
         */
        if (empty($images)) {
            $images = $this->getGalleryImages($product->parent);
        }

        return $images;
    }

    /**
     * Get product variant image if available otherwise product base image.
     *
     * @param  \Webkul\Customer\Contracts\Wishlist  $item
     * @return array
     */
    public function getProductImage($item)
    {
        if ($item instanceof Wishlist) {
            if (isset($item->additional['selected_configurable_option'])) {
                $product = $this->productRepository->find($item->additional['selected_configurable_option']);
            } else {
                $product = $item->product;
            }
        } else {
            $product = $item->product;
        }

        return $this->getProductBaseImage($product);
    }

    /**
     * This method will first check whether the gallery images are already
     * present or not. If not then it will load from the product.
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @param  array
     * @return array
     */
    public function getProductBaseImage($product, ?array $galleryImages = null)
    {
        if (! $product) {
            return;
        }

        return $galleryImages
            ? $galleryImages[0]
            : $this->otherwiseLoadFromProduct($product);
    }

    /**
     * Load product's base image.
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return array
     */
    protected function otherwiseLoadFromProduct($product)
    {
        $images = $product?->images;

        return $images && $images->count()
            ? $this->getCachedImageUrls($images[0]->path)
            : $this->getFallbackImageUrls();
    }

    /**
     * Get cached urls configured for intervention package.
     *
     * @param  string  $path
     */
    private function getCachedImageUrls($path): array
    {
        // Check if the path is already a complete URL (Cloudinary, S3, etc.)
        if ($this->isCompleteUrl($path)) {
            // For Cloudinary URLs, apply transformations for different sizes
            if ($this->isCloudinaryUrl($path)) {
                return [
                    'small_image_url'    => $this->transformCloudinaryUrl($path, 'small'),
                    'medium_image_url'   => $this->transformCloudinaryUrl($path, 'medium'),
                    'large_image_url'    => $this->transformCloudinaryUrl($path, 'large'),
                    'original_image_url' => $path,
                ];
            }
            
            // For other complete URLs (S3, etc.), return them directly
            return [
                'small_image_url'    => $path,
                'medium_image_url'   => $path,
                'large_image_url'    => $path,
                'original_image_url' => $path,
            ];
        }

        $disk = $this->storageService->getDisk();
        
        if ($disk !== 'public' && $disk !== 'local') {
            // For cloud storage with relative paths, use StorageService getFileUrl()
            return [
                'small_image_url'    => $this->storageService->getFileUrl($path),
                'medium_image_url'   => $this->storageService->getFileUrl($path),
                'large_image_url'    => $this->storageService->getFileUrl($path),
                'original_image_url' => $this->storageService->getFileUrl($path),
            ];
        }

        // For local storage, use cached URLs
        return [
            'small_image_url'    => url('cache/small/'.$path),
            'medium_image_url'   => url('cache/medium/'.$path),
            'large_image_url'    => url('cache/large/'.$path),
            'original_image_url' => url('cache/original/'.$path),
        ];
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
     * Check if the given path is a Cloudinary URL.
     *
     * @param  string  $path
     * @return bool
     */
    private function isCloudinaryUrl(string $path): bool
    {
        return $this->isCompleteUrl($path) && 
               (str_contains($path, 'cloudinary.com') || str_contains($path, 'res.cloudinary.com'));
    }

    /**
     * Transform Cloudinary URL for different image sizes.
     *
     * @param  string  $url
     * @param  string  $size
     * @return string
     */
    private function transformCloudinaryUrl(string $url, string $size): string
    {
        // Define size transformations
        $transformations = [
            'small'  => 'w_300,h_300,c_fill,f_auto,q_auto',
            'medium' => 'w_600,h_600,c_fill,f_auto,q_auto',
            'large'  => 'w_1200,h_1200,c_fill,f_auto,q_auto',
        ];

        if (!isset($transformations[$size])) {
            return $url;
        }

        // Check if URL already has transformations
        if (preg_match('/\/image\/upload\/([^\/]+)\//', $url, $matches)) {
            // Replace existing transformations
            $newTransformation = $transformations[$size];
            return str_replace($matches[1], $newTransformation, $url);
        } elseif (str_contains($url, '/image/upload/')) {
            // Add transformations after /image/upload/
            return str_replace('/image/upload/', '/image/upload/' . $transformations[$size] . '/', $url);
        }

        // If it's a Cloudinary URL but doesn't match expected pattern, return as-is
        return $url;
    }

    /**
     * Get fallback urls.
     */
    private function getFallbackImageUrls(): array
    {
        $smallImageUrl = core()->getConfigData('catalog.products.cache_small_image.url')
                        ? $this->storageService->getFileUrl(core()->getConfigData('catalog.products.cache_small_image.url'))
                        : bagisto_asset('images/small-product-placeholder.webp', 'shop');

        $mediumImageUrl = core()->getConfigData('catalog.products.cache_medium_image.url')
                        ? $this->storageService->getFileUrl(core()->getConfigData('catalog.products.cache_medium_image.url'))
                        : bagisto_asset('images/medium-product-placeholder.webp', 'shop');

        $largeImageUrl = core()->getConfigData('catalog.products.cache_large_image.url')
                        ? $this->storageService->getFileUrl(core()->getConfigData('catalog.products.cache_large_image.url'))
                        : bagisto_asset('images/large-product-placeholder.webp', 'shop');

        return [
            'small_image_url'    => $smallImageUrl,
            'medium_image_url'   => $mediumImageUrl,
            'large_image_url'    => $largeImageUrl,
            'original_image_url' => bagisto_asset('images/large-product-placeholder.webp', 'shop'),
        ];
    }
}
