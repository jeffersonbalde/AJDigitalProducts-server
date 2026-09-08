<?php

use App\Models\Admin;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows an admin to upload a product file via chunked upload and stores it on the configured disk', function () {
    Storage::fake('public');
    config(['products.storage_disk' => 'public']);

    $admin = Admin::factory()->create();
    Sanctum::actingAs($admin);

    $product = Product::create([
        'title' => 'Chunk Product',
        'slug' => 'chunk-product',
        'price' => 99.99,
        'category' => 'Digital',
        'subtitle' => 'Sub',
        'description' => 'Desc',
        'is_active' => true,
    ]);

    $originalName = 'test.pdf';
    $content = str_repeat('A', 1500) . str_repeat('B', 1500) . str_repeat('C', 1500); // 4500 bytes
    $fileSize = strlen($content);

    $chunkSize = 1024; // 1KB
    $totalChunks = (int) ceil($fileSize / $chunkSize);

    // init
    $initResp = $this->postJson("/api/products/{$product->id}/file-upload/init", [
        'file_name' => $originalName,
        'file_size' => $fileSize,
        'total_chunks' => $totalChunks,
    ]);

    $initResp->assertSuccessful()
        ->assertJsonPath('success', true);

    $uploadId = $initResp->json('upload_id');
    expect($uploadId)->not->toBeNull();

    // upload chunks
    for ($i = 0; $i < $totalChunks; $i++) {
        $start = $i * $chunkSize;
        $chunkContent = substr($content, $start, $chunkSize);

        $tmpPath = tempnam(sys_get_temp_dir(), 'chunk_');
        file_put_contents($tmpPath, $chunkContent);

        $uploadedChunk = new UploadedFile(
            $tmpPath,
            'chunk.part',
            'application/octet-stream',
            null,
            true
        );

        $chunkResp = $this->post("/api/products/{$product->id}/file-upload/chunk", [
            'upload_id' => $uploadId,
            'chunk_index' => $i,
            'total_chunks' => $totalChunks,
            'chunk' => $uploadedChunk,
        ]);

        $chunkResp->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('chunk_index', $i);
    }

    // complete
    $completeResp = $this->postJson("/api/products/{$product->id}/file-upload/complete", [
        'upload_id' => $uploadId,
    ]);

    $completeResp->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('product.file_name', $originalName)
        ->assertJsonPath('product.file_size', $fileSize);

    $product->refresh();
    expect($product->file_path)->not->toBeNull();

    Storage::disk('public')->assertExists($product->file_path);
    expect(Storage::disk('public')->get($product->file_path))->toBe($content);
});


