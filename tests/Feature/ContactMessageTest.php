<?php

use App\Models\Admin;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer can submit a contact message', function () {
    $payload = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '09171234567',
        'comment' => 'Need help with my order.',
    ];

    $response = $this->postJson('/api/contact-messages', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('contact.email', $payload['email']);

    $message = ContactMessage::first();
    expect($message)->not->toBeNull()
        ->and($message->email)->toBe($payload['email'])
        ->and($message->comment)->toBe($payload['comment']);
});

test('admin can list contact messages', function () {
    ContactMessage::factory()->count(3)->create();
    $admin = Admin::factory()->create();
    $token = $admin->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/contact-messages');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'messages');
});
