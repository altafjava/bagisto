<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Cloudinary integration in Bagisto.
    | This allows switching between local and Cloudinary storage.
    |
    */

    'enabled' => env('CLOUDINARY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Storage Fallback
    |--------------------------------------------------------------------------
    |
    | When enabled, if Cloudinary upload fails, the system will automatically
    | fallback to local storage to ensure uploads don't fail.
    |
    */

    'fallback_to_local' => env('CLOUDINARY_FALLBACK_TO_LOCAL', true),

    /*
    |--------------------------------------------------------------------------
    | Default Upload Options
    |--------------------------------------------------------------------------
    |
    | Default options for Cloudinary uploads. These can be overridden
    | on a per-upload basis.
    |
    */

    'default_options' => [
        'image' => [
            'quality' => 'auto',
            'fetch_format' => 'auto',
            'format' => 'webp',
        ],
        'video' => [
            'quality' => 'auto',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Folder Structure
    |--------------------------------------------------------------------------
    |
    | Define the folder structure for different types of uploads.
    | This helps organize files in Cloudinary.
    |
    */

    'folders' => [
        'products' => 'bagisto/products',
        'categories' => 'bagisto/categories',
        'channels' => 'bagisto/channels',
        'themes' => 'bagisto/themes',
        'customers' => 'bagisto/customers',
        'locales' => 'bagisto/locales',
    ],
];