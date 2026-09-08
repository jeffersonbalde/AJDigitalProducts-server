<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListProductReviewRequest;
use App\Http\Requests\StoreProductReviewRequest;
use App\Http\Requests\UpdateProductReviewRequest;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProductReviewController extends Controller
{
    public function index(ListProductReviewRequest $request): JsonResponse
    {
        $query = ProductReview::query()->with(['product:id,title,slug,thumbnail_image']);

        $search = $request->validated('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $status = $request->validated('status');
        if ($status) {
            $query->where('status', $status);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->validated('is_active'));
        }

        $productId = $request->validated('product_id');
        if ($productId) {
            $query->where('product_id', $productId);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->integer('per_page', 15);
        $reviews = $query->paginate($perPage);
        $items = $reviews->getCollection()->map(function (ProductReview $review) {
            return $this->formatReview($review);
        })->values();

        return response()->json([
            'success' => true,
            'reviews' => $items,
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'from' => $reviews->firstItem(),
                'to' => $reviews->lastItem(),
            ],
        ]);
    }

    public function store(StoreProductReviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $imagePaths = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imageName = time().'_review_'.$image->getClientOriginalName();
                $imagePaths[] = $image->storeAs('product-reviews', $imageName, 'public');
            }
        }

        $review = ProductReview::create([
            'product_id' => $validated['product_id'],
            'rating' => $validated['rating'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => 'pending',
            'is_active' => false,
            'images' => $imagePaths,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted and pending approval.',
            'review' => $this->formatReview($review),
        ], 201);
    }

    public function indexByProduct(Product $product): JsonResponse
    {
        $reviews = $product->reviews()
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
        $items = $reviews->map(function (ProductReview $review) {
            return $this->formatReview($review);
        })->values();

        return response()->json([
            'success' => true,
            'reviews' => $items,
        ]);
    }

    public function update(UpdateProductReviewRequest $request, ProductReview $productReview): JsonResponse
    {
        $validated = $request->validated();

        $payload = [];
        foreach (['rating', 'title', 'content', 'name', 'email'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (array_key_exists('is_active', $validated)) {
            $payload['is_active'] = (bool) $validated['is_active'];
            $payload['status'] = $validated['is_active'] ? 'approved' : 'pending';
        }

        if (! empty($payload)) {
            $productReview->update($payload);
        }

        return response()->json([
            'success' => true,
            'review' => $this->formatReview($productReview->refresh()),
        ]);
    }

    public function destroy(ProductReview $productReview): JsonResponse
    {
        $imagePaths = $productReview->images ?? [];
        foreach ($imagePaths as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
        $productReview->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted.',
        ]);
    }

    private function formatReview(ProductReview $review): array
    {
        $imageUrls = collect($review->images ?? [])
            ->filter()
            ->map(function (string $path) {
                return Storage::disk('public')->url($path);
            })
            ->values()
            ->all();

        $productImageUrl = null;
        $product = $review->product;
        if ($product && $product->thumbnail_image) {
            $productImageUrl = str_starts_with($product->thumbnail_image, 'http')
                ? $product->thumbnail_image
                : Storage::disk('public')->url($product->thumbnail_image);
        }

        return [
            'id' => $review->id,
            'product_id' => $review->product_id,
            'rating' => $review->rating,
            'title' => $review->title,
            'content' => $review->content,
            'name' => $review->name,
            'email' => $review->email,
            'status' => $review->status,
            'is_active' => $review->is_active,
            'images' => $review->images ?? [],
            'image_urls' => $imageUrls,
            'created_at' => $review->created_at,
            'product' => $product ? [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'thumbnail_image' => $product->thumbnail_image,
                'thumbnail_url' => $productImageUrl,
            ] : null,
        ];
    }
}
