<?php

/**
 * Simple script to upload product images from local to DigitalOcean
 * 
 * Usage: php upload-images.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

echo "=== Product Image Upload Script ===\n\n";

// Configuration
$serverUrl = 'https://ajcreativestudio-server-y4duu.ondigitalocean.app';
$token = readline('Enter your admin token (get it from browser DevTools when logged into admin): ');

if (empty($token)) {
    die("Error: Admin token is required\n");
}

echo "\nConnecting to: {$serverUrl}\n";
echo "Checking products...\n\n";

$products = Product::whereNotNull('thumbnail_image')->get();
echo "Found {$products->count()} products with thumbnail images\n\n";

$uploaded = 0;
$skipped = 0;
$failed = 0;

foreach ($products as $product) {
    echo "Processing: {$product->title} (ID: {$product->id})\n";
    echo "  Path: {$product->thumbnail_image}\n";
    
    // Check if file exists locally
    if (!Storage::disk('public')->exists($product->thumbnail_image)) {
        echo "  ⚠ SKIPPED: File not found locally\n\n";
        $skipped++;
        continue;
    }
    
    // Read local file
    $localPath = Storage::disk('public')->path($product->thumbnail_image);
    $fileContent = file_get_contents($localPath);
    $fileSize = strlen($fileContent);
    $base64Data = base64_encode($fileContent);
    
    echo "  File size: " . number_format($fileSize) . " bytes\n";
    echo "  Uploading...\n";
    
    // Upload to server
    try {
        $response = Http::timeout(30)
            ->withToken($token)
            ->post("{$serverUrl}/api/admin/products/images/upload", [
                'path' => $product->thumbnail_image,
                'file_data' => $base64Data,
            ]);
        
        if ($response->successful()) {
            $result = $response->json();
            if ($result['success'] ?? false) {
                echo "  ✓ UPLOADED successfully\n\n";
                $uploaded++;
            } else {
                echo "  ✗ FAILED: " . ($result['message'] ?? 'Unknown error') . "\n\n";
                $failed++;
            }
        } else {
            echo "  ✗ FAILED: HTTP {$response->status()} - " . $response->body() . "\n\n";
            $failed++;
        }
    } catch (\Exception $e) {
        echo "  ✗ ERROR: " . $e->getMessage() . "\n\n";
        $failed++;
    }
    
    // Small delay to avoid overwhelming the server
    usleep(500000); // 0.5 seconds
}

echo "\n=== Summary ===\n";
echo "Uploaded: {$uploaded}\n";
echo "Skipped: {$skipped}\n";
echo "Failed: {$failed}\n";
echo "\nDone!\n";

