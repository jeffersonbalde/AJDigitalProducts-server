<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListProductFaqRequest;
use App\Http\Requests\StoreProductFaqRequest;
use App\Http\Requests\UpdateProductFaqRequest;
use App\Models\Product;
use App\Models\ProductFaq;
use Illuminate\Http\JsonResponse;

class ProductFaqController extends Controller
{
    public function index(ListProductFaqRequest $request): JsonResponse
    {
        $query = ProductFaq::query();

        $search = $request->validated('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', '%'.$search.'%')
                    ->orWhere('answer', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->validated('is_active'));
        }

        $query->orderBy('display_order')
            ->orderBy('created_at');

        $perPage = $request->integer('per_page', 15);
        $faqs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'faqs' => $faqs->items(),
            'pagination' => [
                'current_page' => $faqs->currentPage(),
                'last_page' => $faqs->lastPage(),
                'per_page' => $faqs->perPage(),
                'total' => $faqs->total(),
                'from' => $faqs->firstItem(),
                'to' => $faqs->lastItem(),
            ],
        ]);
    }

    public function store(StoreProductFaqRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $faq = ProductFaq::create([
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'display_order' => $validated['display_order'] ?? 1,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FAQ created.',
            'faq' => $faq,
        ], 201);
    }

    public function update(UpdateProductFaqRequest $request, ProductFaq $productFaq): JsonResponse
    {
        $validated = $request->validated();

        $productFaq->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'FAQ updated.',
            'faq' => $productFaq,
        ]);
    }

    public function destroy(ProductFaq $productFaq): JsonResponse
    {
        $productFaq->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ deleted.',
        ]);
    }

    public function indexByProduct(Product $product): JsonResponse
    {
        $faqs = ProductFaq::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'faqs' => $faqs,
        ]);
    }
}
