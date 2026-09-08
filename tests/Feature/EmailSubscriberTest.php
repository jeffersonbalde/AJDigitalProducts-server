<?php

use App\Models\Admin;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can store email subscriber', function () {
    $response = $this->postJson('/api/email-subscribers', [
        'email' => 'subscriber@example.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('already_subscribed', false);

    expect(EmailSubscriber::count())->toBe(1)
        ->and(EmailSubscriber::first()->email)->toBe('subscriber@example.com');
});

test('returns already subscribed for duplicate email', function () {
    EmailSubscriber::factory()->create([
        'email' => 'duplicate@example.com',
    ]);

    $response = $this->postJson('/api/email-subscribers', [
        'email' => 'duplicate@example.com',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('already_subscribed', true);

    expect(EmailSubscriber::count())->toBe(1);
});

test('admin can list subscribers', function () {
    EmailSubscriber::factory()->count(3)->create();
    $admin = Admin::factory()->create();
    $token = $admin->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/email-subscribers?per_page=2');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('pagination.total', 3)
        ->assertJsonStructure([
            'success',
            'subscribers',
            'pagination' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'from',
                'to',
            ],
        ]);
});
