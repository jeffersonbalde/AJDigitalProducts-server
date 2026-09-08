<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows an authenticated admin to change password with correct current password', function () {
    $admin = Admin::factory()->create([
        'password' => bcrypt('old-password-123'),
    ]);

    Sanctum::actingAs($admin);

    $response = $this->putJson('/api/admin/change-password', [
        'current_password' => 'old-password-123',
        'new_password' => 'new-password-123',
        'new_password_confirmation' => 'new-password-123',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
        ]);

    $admin->refresh();
    expect(Hash::check('new-password-123', $admin->password))->toBeTrue();
});

it('rejects password change when current password is incorrect', function () {
    $admin = Admin::factory()->create([
        'password' => bcrypt('old-password-123'),
    ]);

    Sanctum::actingAs($admin);

    $response = $this->putJson('/api/admin/change-password', [
        'current_password' => 'wrong-password',
        'new_password' => 'new-password-123',
        'new_password_confirmation' => 'new-password-123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.current_password.0', 'Current password is incorrect.');
});
