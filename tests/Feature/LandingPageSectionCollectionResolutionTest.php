<?php

use App\Models\Admin;
use App\Models\LandingPageSection;
use App\Models\ProductCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows updating a product_grid section when source_value is a collection id string', function () {
    $admin = Admin::factory()->create();
    $token = $admin->createToken('test-token')->plainTextToken;

    $collection = ProductCollection::create([
        'name' => 'Best Sellers',
        'slug' => 'best-sellers',
        'is_active' => true,
    ]);

    $section = LandingPageSection::create([
        'title' => 'Best Sellers',
        'section_type' => 'product_grid',
        'source_type' => 'collection',
        'source_value' => (string) $collection->id,
        'product_count' => 4,
        'display_style' => 'grid',
        'is_active' => true,
        'display_order' => 1,
        'status' => 'draft',
    ]);

    $response = $this
        ->withHeader('Authorization', "Bearer $token")
        ->putJson("/api/landing-page-sections/{$section->id}", [
            'section_type' => 'product_grid',
            'is_active' => false,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('section.id', $section->id)
        ->assertJsonPath('section.is_active', false);
});
