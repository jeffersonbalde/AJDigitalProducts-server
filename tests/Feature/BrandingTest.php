<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns branding via public endpoint', function () {
    $response = $this->getJson('/api/branding');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'branding' => [
                'logo_text',
                'logo_path',
                'logo_url',
            ],
        ]);
});

it('allows an admin to update branding text and logo', function () {
    Storage::fake('public');

    $admin = Admin::factory()->create();
    Sanctum::actingAs($admin);

    // Use create() instead of image() so the test doesn't require the GD extension.
    $file = UploadedFile::fake()->create('logo.png', 50, 'image/png');

    $response = $this->post('/api/admin/branding', [
        'logo_text' => 'My Brand',
        'logo' => $file,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('branding.logo_text', 'My Brand')
        ->assertJsonPath('success', true);

    $path = $response->json('branding.logo_path');
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    expect($response->json('branding.logo_url'))->toStartWith('/api/branding/logo?v=');
});

it('rejects branding updates from non-admin users', function () {
    $response = $this->postJson('/api/admin/branding', [
        'logo_text' => 'Nope',
    ]);

    $response->assertStatus(401);
});
