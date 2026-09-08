<?php

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductFaq;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer can view active product faqs', function () {
    $product = Product::create([
        'title' => 'Test Product',
        'slug' => 'test-product',
        'price' => 199.99,
        'category' => 'Digital',
        'is_active' => true,
    ]);
    ProductFaq::factory()->create([
        'is_active' => true,
    ]);
    ProductFaq::factory()->create([
        'is_active' => false,
    ]);

    $response = $this->getJson("/api/products/{$product->id}/faqs");

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'faqs');
});

test('admin can create a product faq', function () {
    $product = Product::create([
        'title' => 'Test Product',
        'slug' => 'test-product-2',
        'price' => 199.99,
        'category' => 'Digital',
        'is_active' => true,
    ]);
    $admin = Admin::factory()->create();
    $token = $admin->createToken('test-token')->plainTextToken;

    $payload = [
        'question' => 'Does it work on mobile?',
        'answer' => 'Yes, it works on mobile and desktop.',
        'display_order' => 1,
        'is_active' => true,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/product-faqs', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('faq.question', $payload['question']);
});
