<?php

use App\Models\Customer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    DB::statement('PRAGMA foreign_keys=ON');

    Schema::dropIfExists('email_verification_otps');
    Schema::dropIfExists('customers');

    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('google_sub')->nullable()->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->boolean('is_active')->default(false);
        $table->string('register_status')->default('pending');
        $table->integer('otp_attempts')->default(0);
        $table->timestamp('otp_sent_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('email_verification_otps', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
        $table->string('otp', 6);
        $table->timestamp('expires_at');
        $table->boolean('is_used')->default(false);
        $table->timestamps();

        $table->index(['customer_id', 'otp', 'is_used']);
    });
});

test('google signup registers a verified customer', function () {
    config()->set('services.google.client_id', 'test-google-client-id');

    Http::fake([
        'oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'test-google-client-id',
            'sub' => 'google-sub-123',
            'email' => 'newuser@example.com',
            'email_verified' => 'true',
            'name' => 'New User',
        ], 200),
    ]);

    $response = $this->postJson('/api/auth/google/signup', [
        'id_token' => 'fake-id-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('customer.email', 'newuser@example.com');

    $customer = Customer::where('email', 'newuser@example.com')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->is_active)->toBeTrue()
        ->and($customer->register_status)->toBe('verified')
        ->and($customer->google_sub)->toBe('google-sub-123')
        ->and($customer->email_verified_at)->not->toBeNull();
});

test('google signup rejects invalid token', function () {
    config()->set('services.google.client_id', 'test-google-client-id');

    Http::fake([
        'oauth2.googleapis.com/tokeninfo*' => Http::response([
            'error' => 'invalid_token',
        ], 400),
    ]);

    $response = $this->postJson('/api/auth/google/signup', [
        'id_token' => 'bad-token',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'GOOGLE_INVALID_TOKEN');
});

test('google signup returns email already registered for verified customers', function () {
    config()->set('services.google.client_id', 'test-google-client-id');

    Customer::create([
        'name' => 'Existing',
        'email' => 'existing@example.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'register_status' => 'verified',
        'email_verified_at' => now(),
    ]);

    Http::fake([
        'oauth2.googleapis.com/tokeninfo*' => Http::response([
            'aud' => 'test-google-client-id',
            'sub' => 'google-sub-existing',
            'email' => 'existing@example.com',
            'email_verified' => 'true',
            'name' => 'Existing User',
        ], 200),
    ]);

    $response = $this->postJson('/api/auth/google/signup', [
        'id_token' => 'fake-id-token',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'EMAIL_ALREADY_REGISTERED');
});
