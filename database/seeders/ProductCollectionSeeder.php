<?php

namespace Database\Seeders;

use App\Models\ProductCollection;
use Illuminate\Database\Seeder;

class ProductCollectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $collections = [
            [
                'name' => 'New Arrivals',
                'slug' => 'new-arrivals',
                'description' => 'Latest products recently added to the store.',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Best Sellers',
                'slug' => 'best-sellers',
                'description' => 'Top-selling products based on customer popularity and sales performance.',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Featured Products',
                'slug' => 'featured-products',
                'description' => 'Hand-picked featured products displayed prominently on the landing page.',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'On Sale',
                'slug' => 'on-sale',
                'description' => 'Products currently on sale with special discounts.',
                'display_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Premium Templates',
                'slug' => 'premium-templates',
                'description' => 'High-quality premium spreadsheet templates and tools.',
                'display_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Business Essentials',
                'slug' => 'business-essentials',
                'description' => 'Essential tools and templates for business management.',
                'display_order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($collections as $collection) {
            ProductCollection::updateOrCreate(
                ['slug' => $collection['slug']],
                $collection
            );
        }
    }
}

