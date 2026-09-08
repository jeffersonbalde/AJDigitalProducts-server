<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Product Upload Storage Disk
    |--------------------------------------------------------------------------
    |
    | This disk is used for product files and images (thumbnails / feature images).
    |
    | - Local / default: "public" (storage/app/public)
    | - DigitalOcean Spaces: set PRODUCT_STORAGE_DISK=s3 (and configure AWS_* env vars)
    |
    */
    'storage_disk' => env('PRODUCT_STORAGE_DISK', 'public'),
];


