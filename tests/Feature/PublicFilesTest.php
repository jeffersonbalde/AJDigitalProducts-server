<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('streams a product storage disk file via /api/storage', function () {
    config(['products.storage_disk' => 'public']);
    Storage::fake('public');
    Storage::disk('public')->put('products/thumbnails/test.png', 'fake');

    $response = $this->get('/api/storage/products/thumbnails/test.png');

    $response->assertSuccessful();
});

it('returns 404 for missing product storage files', function () {
    config(['products.storage_disk' => 'public']);
    Storage::fake('public');

    $response = $this->get('/api/storage/does-not-exist.png');

    $response->assertNotFound();
});
