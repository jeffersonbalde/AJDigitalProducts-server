<?php

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer can submit a product review', function () {
    $product = Product::create([
        'title' => 'Test Product',
        'slug' => 'test-product',
        'price' => 199.99,
        'category' => 'Digital',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/product-reviews', [
        'product_id' => $product->id,
        'rating' => 5,
        'title' => 'Great template',
        'content' => 'This was super helpful for my workflow.',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('review.status', 'pending')
        ->assertJsonPath('review.is_active', false);

    $review = ProductReview::first();
    expect($review)->not->toBeNull()
        ->and($review->product_id)->toBe($product->id)
        ->and($review->status)->toBe('pending')
        ->and($review->is_active)->toBeFalse();
});

test('product reviews endpoint returns only approved active reviews', function () {
    $product = Product::create([
        'title' => 'Test Product',
        'slug' => 'test-product',
        'price' => 199.99,
        'category' => 'Digital',
        'is_active' => true,
    ]);
    ProductReview::create([
        'product_id' => $product->id,
        'rating' => 5,
        'title' => 'Great template',
        'content' => 'This was super helpful for my workflow.',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'status' => 'approved',
        'is_active' => true,
    ]);
    ProductReview::create([
        'product_id' => $product->id,
        'rating' => 4,
        'title' => 'Pending review',
        'content' => 'Waiting for approval.',
        'name' => 'Sam Doe',
        'email' => 'sam@example.com',
        'status' => 'pending',
        'is_active' => false,
    ]);

    $response = $this->getJson("/api/products/{$product->id}/reviews");

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'reviews');
});

test('admin can approve a review', function () {
    $review = ProductReview::create([
        'product_id' => Product::create([
            'title' => 'Test Product',
            'slug' => 'test-product-2',
            'price' => 199.99,
            'category' => 'Digital',
            'is_active' => true,
        ])->id,
        'rating' => 5,
        'title' => 'Pending',
        'content' => 'Waiting approval.',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'status' => 'pending',
        'is_active' => false,
    ]);
    $admin = Admin::factory()->create();
    $token = $admin->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/product-reviews/{$review->id}", [
            'is_active' => true,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('review.status', 'approved')
        ->assertJsonPath('review.is_active', true);
});
