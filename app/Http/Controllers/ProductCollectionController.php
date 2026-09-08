<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductCollectionController extends Controller
{
    /**
     * Display a listing of collections
     */
    public function index(Request $request)
    {
        $query = ProductCollection::withCount('products');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'display_order');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $collections = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'collections' => $collections->items(),
            'pagination' => [
                'current_page' => $collections->currentPage(),
                'last_page' => $collections->lastPage(),
                'per_page' => $collections->perPage(),
                'total' => $collections->total(),
            ],
        ]);
    }

    /**
     * Get all collections (for dropdowns)
     */
    public function list()
    {
        $collections = ProductCollection::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'collections' => $collections,
        ]);
    }

    /**
     * Public list of active collections with their active products (for storefront)
     */
    public function publicListWithProducts()
    {
        $collections = ProductCollection::where('is_active', true)
            ->with(['products' => function ($query) {
                $query->where('is_active', true)
                    ->select(
                        'products.id',
                        'products.title',
                        'products.price',
                        'products.old_price',
                        'products.on_sale',
                        'products.slug',
                        'products.category',
                        'products.thumbnail_image'
                    );
            }])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'collections' => $collections,
        ]);
    }

    /**
     * Store a newly created collection
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:product_collections,name',
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer|min:0|unique:product_collections,display_order',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (ProductCollection::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }
            $data['slug'] = $slug;
        }

        $collection = ProductCollection::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Collection created successfully',
            'collection' => $collection,
        ], 201);
    }

    /**
     * Display the specified collection by slug (public route)
     */
    public function showBySlug($slug)
    {
        $collection = ProductCollection::where('slug', $slug)
            ->where('is_active', true)
            ->with(['products' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('collection_product.display_order')
                    ->orderBy('collection_product.added_at', 'desc');
            }])
            ->first();

        if (! $collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'collection' => $collection,
        ]);
    }

    /**
     * Display the specified collection
     */
    public function show($id)
    {
        $collection = ProductCollection::with(['products' => function ($query) {
            $query->orderBy('collection_product.display_order')
                ->orderBy('collection_product.added_at', 'desc');
        }])->find($id);

        if (! $collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found',
            ], 404);
        }

        // Ensure products are loaded
        $collection->load('products');

        return response()->json([
            'success' => true,
            'collection' => $collection,
        ]);
    }

    /**
     * Update the specified collection
     */
    public function update(Request $request, $id)
    {
        $collection = ProductCollection::find($id);

        if (! $collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:product_collections,name,'.$id,
            'slug' => 'sometimes|string|max:255|unique:product_collections,slug,'.$id,
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer|min:0|unique:product_collections,display_order,'.$id,
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Generate slug if name changed and slug not provided
        if (isset($data['name']) && $data['name'] !== $collection->name && ! isset($data['slug'])) {
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (ProductCollection::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }
            $data['slug'] = $slug;
        }

        $collection->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Collection updated successfully',
            'collection' => $collection->fresh(),
        ]);
    }

    /**
     * Remove the specified collection
     */
    public function destroy($id)
    {
        $collection = ProductCollection::find($id);

        if (! $collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found',
            ], 404);
        }

        $collection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Collection deleted successfully',
        ]);
    }

    /**
     * Add products to collection
     */
    public function addProducts(Request $request, $id)
    {
        $collection = ProductCollection::find($id);

        if (! $collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $productIds = $request->product_ids;
        $displayOrder = $request->get('display_order', 0);

        foreach ($productIds as $index => $productId) {
            $collection->products()->syncWithoutDetaching([
                $productId => [
                    'display_order' => $displayOrder + $index,
                    'added_at' => now(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Products added to collection successfully',
        ]);
    }

    /**
     * Remove products from collection
     */
    public function removeProducts(Request $request, $id)
    {
        $collection = ProductCollection::find($id);

        if (! $collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $collection->products()->detach($request->product_ids);

        return response()->json([
            'success' => true,
            'message' => 'Products removed from collection successfully',
        ]);
    }

    /**
     * Update product order in collection
     */
    public function updateProductOrder(Request $request, $id)
    {
        $collection = ProductCollection::find($id);

        if (! $collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'product_orders' => 'required|array',
            'product_orders.*.product_id' => 'required|exists:products,id',
            'product_orders.*.display_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->product_orders as $order) {
            $collection->products()->updateExistingPivot($order['product_id'], [
                'display_order' => $order['display_order'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product order updated successfully',
        ]);
    }
}
