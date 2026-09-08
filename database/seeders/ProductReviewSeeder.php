<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Database\Seeder;

class ProductReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $product = Product::query()->first();
        if (! $product) {
            $product = Product::factory()->create([
                'title' => 'Sample Product',
                'slug' => 'sample-product',
                'price' => 199,
                'category' => 'Digital',
            ]);
        }

        ProductReview::factory()->count(3)->create([
            'product_id' => $product->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        ProductReview::factory()->create([
            'product_id' => $product->id,
            'status' => 'pending',
            'is_active' => false,
        ]);
    }
}
