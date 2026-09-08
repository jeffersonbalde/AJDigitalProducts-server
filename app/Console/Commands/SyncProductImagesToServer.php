<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class SyncProductImagesToServer extends Command
{
    protected $signature = 'products:sync-images {--server-url=} {--token=} {--dry-run}';
    protected $description = 'Sync product images from local storage to remote server (DigitalOcean)';

    public function handle()
    {
        $serverUrl = $this->option('server-url') ?: env('APP_URL', 'https://ajcreativestudio-server-y4duu.ondigitalocean.app');
        $token = $this->option('token');
        $dryRun = $this->option('dry-run');
        
        if (!$token) {
            $this->error('Please provide an admin token using --token option');
            $this->info('You can get a token by logging into the admin panel and checking your browser\'s localStorage or session');
            return Command::FAILURE;
        }
        
        $this->info("Syncing product images to: {$serverUrl}");
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be uploaded');
        }
        
        $products = Product::whereNotNull('thumbnail_image')->get();
        $this->info("Found {$products->count()} products with thumbnail images");
        
        $uploaded = 0;
        $skipped = 0;
        $failed = 0;
        
        foreach ($products as $product) {
            $this->line("Processing: {$product->title} (ID: {$product->id})");
            
            // Check if file exists locally
            if (!Storage::disk('public')->exists($product->thumbnail_image)) {
                $this->warn("  ⚠ Local file not found: {$product->thumbnail_image}");
                $skipped++;
                continue;
            }
            
            // Read local file
            $localPath = Storage::disk('public')->path($product->thumbnail_image);
            $fileContent = file_get_contents($localPath);
            $base64Data = base64_encode($fileContent);
            
            if ($dryRun) {
                $this->info("  [DRY RUN] Would upload: {$product->thumbnail_image} (" . number_format(strlen($fileContent)) . " bytes)");
                $uploaded++;
                continue;
            }
            
            // Upload to server
            try {
                $response = Http::withToken($token)
                    ->post("{$serverUrl}/api/admin/products/images/upload", [
                        'path' => $product->thumbnail_image,
                        'file_data' => $base64Data,
                    ]);
                
                if ($response->successful()) {
                    $this->info("  ✓ Uploaded: {$product->thumbnail_image}");
                    $uploaded++;
                } else {
                    $this->error("  ✗ Failed: {$product->thumbnail_image} - " . $response->body());
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Error: {$product->thumbnail_image} - " . $e->getMessage());
                $failed++;
            }
            
            // Also sync feature images
            if ($product->feature_images && is_array($product->feature_images)) {
                foreach ($product->feature_images as $featurePath) {
                    if (!Storage::disk('public')->exists($featurePath)) {
                        $this->warn("  ⚠ Feature image not found: {$featurePath}");
                        continue;
                    }
                    
                    $featureContent = file_get_contents(Storage::disk('public')->path($featurePath));
                    $featureBase64 = base64_encode($featureContent);
                    
                    if ($dryRun) {
                        $this->info("  [DRY RUN] Would upload feature: {$featurePath}");
                        continue;
                    }
                    
                    try {
                        $response = Http::withToken($token)
                            ->post("{$serverUrl}/api/admin/products/images/upload", [
                                'path' => $featurePath,
                                'file_data' => $featureBase64,
                            ]);
                        
                        if ($response->successful()) {
                            $this->info("  ✓ Uploaded feature: {$featurePath}");
                        } else {
                            $this->error("  ✗ Failed feature: {$featurePath}");
                        }
                    } catch (\Exception $e) {
                        $this->error("  ✗ Error feature: {$featurePath} - " . $e->getMessage());
                    }
                }
            }
        }
        
        $this->newLine();
        $this->info("Summary:");
        $this->info("  Uploaded: {$uploaded}");
        $this->info("  Skipped: {$skipped}");
        $this->info("  Failed: {$failed}");
        
        return Command::SUCCESS;
    }
}

