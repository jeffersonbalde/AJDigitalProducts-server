<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SyncProductImagesController extends Controller
{
    /**
     * Sync product images - check which files are missing and provide info
     * Also check if files exist in other possible locations
     */
    public function checkMissing(Request $request): JsonResponse
    {
        $products = Product::whereNotNull('thumbnail_image')->get();
        
        $missing = [];
        $found = [];
        $alternativeLocations = [];
        
        foreach ($products as $product) {
            $thumbnailExists = Storage::disk('public')->exists($product->thumbnail_image);
            
            // Check alternative locations
            $altPaths = [
                'public/' . $product->thumbnail_image,
                'storage/app/public/' . $product->thumbnail_image,
                $product->thumbnail_image,
            ];
            
            $foundInAlt = null;
            foreach ($altPaths as $altPath) {
                if (Storage::disk('public')->exists($altPath)) {
                    $foundInAlt = $altPath;
                    break;
                }
            }
            
            if (!$thumbnailExists && !$foundInAlt) {
                $missing[] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'thumbnail_path' => $product->thumbnail_image,
                    'database_path' => $product->thumbnail_image,
                ];
            } else {
                $found[] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'thumbnail_path' => $foundInAlt ?: $product->thumbnail_image,
                    'database_path' => $product->thumbnail_image,
                    'found_in_alt_location' => $foundInAlt !== null,
                ];
            }
            
            if ($foundInAlt) {
                $alternativeLocations[] = [
                    'product_id' => $product->id,
                    'database_path' => $product->thumbnail_image,
                    'found_at' => $foundInAlt,
                ];
            }
        }
        
        // Check all files in storage to see what actually exists
        $allThumbnails = Storage::disk('public')->allFiles('products/thumbnails');
        $allFeatures = Storage::disk('public')->allFiles('products/features');
        
        return response()->json([
            'success' => true,
            'missing_count' => count($missing),
            'found_count' => count($found),
            'missing' => $missing,
            'found' => $found,
            'alternative_locations' => $alternativeLocations,
            'all_files_on_disk' => [
                'thumbnails' => array_slice($allThumbnails, 0, 50),
                'features' => array_slice($allFeatures, 0, 50),
            ],
            'storage_root' => Storage::disk('public')->path(''),
        ]);
    }
    
    /**
     * Upload a file to storage (for syncing missing files)
     * This endpoint accepts base64 encoded file data or file upload
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
            'file' => 'required_without:file_data|file',
            'file_data' => 'required_without:file|string', // base64 encoded
        ]);
        
        $path = $request->input('path');
        $directory = dirname($path);
        $filename = basename($path);
        
        // Ensure directory exists
        Storage::disk('public')->makeDirectory($directory);
        
        try {
            if ($request->hasFile('file')) {
                // Handle file upload
                $file = $request->file('file');
                $storedPath = $file->storeAs($directory, $filename, 'public');
            } elseif ($request->has('file_data')) {
                // Handle base64 encoded file
                $fileData = $request->input('file_data');
                
                // Remove data URL prefix if present
                if (strpos($fileData, 'data:') === 0) {
                    $fileData = preg_replace('/^data:[^;]+;base64,/', '', $fileData);
                }
                
                $decoded = base64_decode($fileData, true);
                if ($decoded === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid base64 data',
                    ], 400);
                }
                
                $fullPath = Storage::disk('public')->path($path);
                Storage::disk('public')->put($path, $decoded);
                $storedPath = $path;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Either file or file_data is required',
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'path' => $storedPath,
                'exists' => Storage::disk('public')->exists($storedPath),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file: ' . $e->getMessage(),
            ], 500);
        }
    }
}

