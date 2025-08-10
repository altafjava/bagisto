<?php

namespace Webkul\Product\Observers;

use Illuminate\Support\Facades\Storage;
use Webkul\Core\Services\StorageService;

class ProductObserver
{
    /**
     * Create a new observer instance.
     *
     * @return void
     */
    public function __construct(
        protected StorageService $storageService
    ) {}

    /**
     * Handle the Product "deleting" event.
     * This runs before the product is actually deleted from the database,
     * ensuring we can still access the images relationship.
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return void
     */
    public function deleting($product)
    {
        // Delete Cloudinary images if they exist
        $this->deleteCloudinaryImages($product);
    }

    /**
     * Handle the Product "deleted" event.
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return void
     */
    public function deleted($product)
    {
        // Delete local storage directory (existing behavior)
        Storage::deleteDirectory('product/'.$product->id);
    }

    /**
     * Delete Cloudinary images for the product.
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return void
     */
    protected function deleteCloudinaryImages($product): void
    {
        try {
            // Get product images - during 'deleting' event, the relationship should be accessible
            $images = $product->images;

            if ($images->isEmpty()) {
                return;
            }

            foreach ($images as $image) {
                // Check if this is a Cloudinary URL and delete it
                if ($this->storageService->isCloudinaryUrl($image->path)) {
                    $this->storageService->deleteFile($image->path);
                    
                    logger()->info('Deleted Cloudinary image for product', [
                        'product_id' => $product->id,
                        'image_path' => $image->path
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the deletion process
            logger()->warning('Failed to delete Cloudinary images for product', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
