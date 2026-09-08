<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::with(['addedByAdmin', 'addedByPersonnel']);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('subtitle', 'like', '%' . $request->search . '%')
                  ->orWhere('category', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by category
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        // Filter by availability
        if ($request->has('availability') && $request->availability) {
            $query->where('availability', $request->availability);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);
        
        // Add added_by_name to each product
        $productsData = $products->map(function($product) {
            $productArray = $product->toArray();
            $productArray['added_by_name'] = $product->added_by_name;
            return $productArray;
        });

        return response()->json([
            'success' => true,
            'products' => $productsData->all(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $disk = config('products.storage_disk', 'public');

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subtitle' => 'required|string',
            'price' => 'required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'on_sale' => 'boolean',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'file' => 'nullable|file|mimes:xlsx,xls,pdf|max:20480', // Optional - Excel or PDF files, Max 20MB (can be uploaded separately)
            'thumbnail_image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:5120', // Max 5MB
            'feature_images' => 'nullable|array',
            'feature_images.*' => 'image|mimes:jpeg,jpg,png,webp,gif|max:5120', // Max 5MB per image
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        
        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('products', $fileName, $disk);
            if (! $filePath || ! Storage::disk($disk)->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store product file on server storage',
                ], 500);
            }
            
            $data['file_path'] = $filePath;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();
        }
        
        // Handle thumbnail image upload
        if ($request->hasFile('thumbnail_image')) {
            $thumbnail = $request->file('thumbnail_image');
            $thumbnailName = time() . '_thumbnail_' . $thumbnail->getClientOriginalName();
            $thumbnailPath = $thumbnail->storeAs('products/thumbnails', $thumbnailName, $disk);
            if (! $thumbnailPath || ! Storage::disk($disk)->exists($thumbnailPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store product thumbnail image on server storage',
                ], 500);
            }
            $data['thumbnail_image'] = $thumbnailPath;
        }
        
        // Handle feature images upload
        if ($request->hasFile('feature_images')) {
            $featureImages = [];
            foreach ($request->file('feature_images') as $index => $image) {
                $imageName = time() . '_feature_' . $index . '_' . $image->getClientOriginalName();
                $imagePath = $image->storeAs('products/features', $imageName, $disk);
                if (! $imagePath || ! Storage::disk($disk)->exists($imagePath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to store one of the feature images on server storage',
                    ], 500);
                }
                $featureImages[] = $imagePath;
            }
            $data['feature_images'] = $featureImages;
        }
        
        // Generate slug from title
        $data['slug'] = Product::createSlug($data['title']);
        
        // Ensure slug is unique
        $baseSlug = $data['slug'];
        $counter = 1;
        while (Product::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $baseSlug . '-' . $counter;
            $counter++;
        }

        // Store the user who created the product
        $user = $request->user();
        if ($user) {
            $data['added_by_user_id'] = $user->id;
            // Determine user type
            if ($user instanceof \App\Models\Admin) {
                $data['added_by_user_type'] = 'admin';
            } elseif ($user instanceof \App\Models\Personnel) {
                $data['added_by_user_type'] = 'personnel';
            }
        }

        $product = Product::create($data);
        $product->load(['addedByAdmin', 'addedByPersonnel']);
        
        // Ensure the added_by_name accessor is available
        $productData = $product->toArray();
        $productData['added_by_name'] = $product->added_by_name;

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'product' => $productData,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $product = Product::with(['addedByAdmin', 'addedByPersonnel'])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }
        
        // Ensure the added_by_name accessor is available
        $productData = $product->toArray();
        $productData['added_by_name'] = $product->added_by_name;

        return response()->json([
            'success' => true,
            'product' => $productData,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        $disk = config('products.storage_disk', 'public');

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        // Get subtitle and description first to validate content
        $subtitle = $request->input('subtitle', '');
        $description = $request->input('description', '');
        
        // Debug: Log what we're receiving
        \Log::info('Product Update Request', [
            'subtitle_present' => $request->has('subtitle'),
            'subtitle_value' => $subtitle ? substr($subtitle, 0, 100) . '...' : 'EMPTY',
            'description_present' => $request->has('description'),
            'description_value' => $description ? substr($description, 0, 100) . '...' : 'EMPTY',
            'all_inputs' => array_keys($request->all()),
        ]);
        
        // Strip HTML tags to check for actual content
        $subtitleText = strip_tags($subtitle);
        $descriptionText = strip_tags($description);
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string', // Allow null/empty; we will fallback to existing
            'price' => 'sometimes|required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'on_sale' => 'boolean',
            'category' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string', // Allow null/empty; we will fallback to existing
            'file' => 'nullable|file|mimes:xlsx,xls,pdf|max:20480', // Optional on update - Excel or PDF files, Max 20MB
            'thumbnail_image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:5120', // Max 5MB
            'feature_images' => 'nullable|array',
            'feature_images.*' => 'image|mimes:jpeg,jpg,png,webp,gif|max:5120', // Max 5MB per image
            'remove_thumbnail' => 'nullable|boolean',
            'remove_feature_images' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        
        // If subtitle/description missing or empty, keep existing values
        $requestData = $request->all();
        $subtitleExists = array_key_exists('subtitle', $requestData);
        $descriptionExists = array_key_exists('description', $requestData);

        if ($subtitleExists && !empty(trim($subtitleText))) {
            $data['subtitle'] = $subtitle;
        } else {
            $data['subtitle'] = $product->subtitle;
        }

        if ($descriptionExists && !empty(trim($descriptionText))) {
            $data['description'] = $description;
        } else {
            $data['description'] = $product->description;
        }

        // Handle file removal
        if ($request->has('remove_file') && $request->remove_file === '1') {
            if ($product->file_path && Storage::disk($disk)->exists($product->file_path)) {
                Storage::disk($disk)->delete($product->file_path);
            }
            $data['file_path'] = null;
            $data['file_name'] = null;
            $data['file_size'] = null;
        }
        
        // Handle file upload
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($product->file_path && Storage::disk($disk)->exists($product->file_path)) {
                Storage::disk($disk)->delete($product->file_path);
            }
            
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('products', $fileName, $disk);
            if (! $filePath || ! Storage::disk($disk)->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store product file on server storage',
                ], 500);
            }
            
            $data['file_path'] = $filePath;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();
        }
        
        // Handle thumbnail image removal
        if ($request->has('remove_thumbnail') && $request->remove_thumbnail === '1') {
            if ($product->thumbnail_image && Storage::disk($disk)->exists($product->thumbnail_image)) {
                Storage::disk($disk)->delete($product->thumbnail_image);
            }
            $data['thumbnail_image'] = null;
        }
        
        // Handle thumbnail image upload
        if ($request->hasFile('thumbnail_image')) {
            // Delete old thumbnail if exists
            if ($product->thumbnail_image && Storage::disk($disk)->exists($product->thumbnail_image)) {
                Storage::disk($disk)->delete($product->thumbnail_image);
            }
            
            $thumbnail = $request->file('thumbnail_image');
            $thumbnailName = time() . '_thumbnail_' . $thumbnail->getClientOriginalName();
            $thumbnailPath = $thumbnail->storeAs('products/thumbnails', $thumbnailName, $disk);
            if (! $thumbnailPath || ! Storage::disk($disk)->exists($thumbnailPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store product thumbnail image on server storage',
                ], 500);
            }
            $data['thumbnail_image'] = $thumbnailPath;
        }
        
        // Handle feature images - need to process removal first, then additions
        $currentFeatureImages = $product->feature_images ?? [];
        if (!is_array($currentFeatureImages)) {
            // If feature_images is stored as JSON string, decode it
            try {
                $currentFeatureImages = json_decode($currentFeatureImages, true) ?? [];
            } catch (\Exception $e) {
                $currentFeatureImages = [];
            }
        }
        
        // First, handle removal
        if ($request->has('remove_feature_images') && is_array($request->remove_feature_images)) {
            foreach ($request->remove_feature_images as $index) {
                if (isset($currentFeatureImages[$index]) && Storage::disk($disk)->exists($currentFeatureImages[$index])) {
                    Storage::disk($disk)->delete($currentFeatureImages[$index]);
                }
            }
            // Remove deleted images from array
            $currentFeatureImages = array_values(array_diff_key($currentFeatureImages, array_flip($request->remove_feature_images)));
        }
        
        // Then, handle new uploads and merge with remaining images
        if ($request->hasFile('feature_images')) {
            $newFeatureImages = [];
            
            foreach ($request->file('feature_images') as $index => $image) {
                $imageName = time() . '_feature_' . $index . '_' . $image->getClientOriginalName();
                $imagePath = $image->storeAs('products/features', $imageName, $disk);
                if (! $imagePath || ! Storage::disk($disk)->exists($imagePath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to store one of the feature images on server storage',
                    ], 500);
                }
                $newFeatureImages[] = $imagePath;
            }
            
            // Merge remaining images (after removal) with new ones, avoiding duplicates
            $existingPaths = array_map('strval', $currentFeatureImages);
            foreach ($newFeatureImages as $newPath) {
                if (!in_array($newPath, $existingPaths, true)) {
                    $currentFeatureImages[] = $newPath;
                    $existingPaths[] = $newPath;
                }
            }
        }
        
        // Only set feature_images if we've made changes
        if ($request->has('remove_feature_images') || $request->hasFile('feature_images')) {
            $data['feature_images'] = $currentFeatureImages;
        }

        // Update slug if title changed
        if (isset($data['title']) && $data['title'] !== $product->title) {
            $data['slug'] = Product::createSlug($data['title']);
            
            // Ensure slug is unique
            $baseSlug = $data['slug'];
            $counter = 1;
            while (Product::where('slug', $data['slug'])->where('id', '!=', $id)->exists()) {
                $data['slug'] = $baseSlug . '-' . $counter;
                $counter++;
            }
        }

        $product->update($data);
        $product->load(['addedByAdmin', 'addedByPersonnel']);
        
        // Ensure the added_by_name accessor is available
        $productData = $product->toArray();
        $productData['added_by_name'] = $product->added_by_name;

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'product' => $productData,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $product = Product::find($id);
        $disk = config('products.storage_disk', 'public');

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        // Delete associated file if exists
        if ($product->file_path && Storage::disk($disk)->exists($product->file_path)) {
            Storage::disk($disk)->delete($product->file_path);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Get categories for filtering
     */
    public function categories()
    {
        $categories = Product::distinct()->pluck('category')->filter()->values();
        
        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Get featured products
     */
    public function getFeatured()
    {
        $products = Product::where('is_featured', true)
            ->where('is_active', true)
            ->with(['addedByAdmin', 'addedByPersonnel'])
            ->orderBy('featured_order')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Add added_by_name to each product
        $productsData = $products->map(function($product) {
            $productArray = $product->toArray();
            $productArray['added_by_name'] = $product->added_by_name;
            return $productArray;
        });
        
        return response()->json([
            'success' => true,
            'products' => $productsData,
        ]);
    }

    /**
     * Get bestseller products
     */
    public function getBestsellers()
    {
        $products = Product::where('is_bestseller', true)
            ->where('is_active', true)
            ->with(['addedByAdmin', 'addedByPersonnel'])
            ->orderBy('bestseller_order')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Add added_by_name to each product
        $productsData = $products->map(function($product) {
            $productArray = $product->toArray();
            $productArray['added_by_name'] = $product->added_by_name;
            return $productArray;
        });
        
        return response()->json([
            'success' => true,
            'products' => $productsData,
        ]);
    }

    /**
     * Get new arrival products
     */
    public function getNewArrivals()
    {
        $products = Product::where('is_new_arrival', true)
            ->where('is_active', true)
            ->with(['addedByAdmin', 'addedByPersonnel'])
            ->orderBy('new_arrival_order')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Add added_by_name to each product
        $productsData = $products->map(function($product) {
            $productArray = $product->toArray();
            $productArray['added_by_name'] = $product->added_by_name;
            return $productArray;
        });
        
        return response()->json([
            'success' => true,
            'products' => $productsData,
        ]);
    }

    /**
     * Download product file
     */
    public function downloadFile($id)
    {
        $product = Product::find($id);
        $disk = config('products.storage_disk', 'public');

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        if (!$product->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Product file not found',
            ], 404);
        }

        // Check if file exists
        if (! Storage::disk($disk)->exists($product->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found on server',
            ], 404);
        }

        $fileName = $product->file_name ?: basename($product->file_path);

        // Return file download response
        return Storage::disk($disk)->download($product->file_path, $fileName);
    }
}
