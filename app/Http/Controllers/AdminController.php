<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * Admin login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',



            
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // For demo purposes, we'll use a simple admin check
        // In production, you should use a proper User model with roles
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('ADMIN_PASSWORD', 'password123');

        if ($request->email !== $adminEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // For demo, we'll do a simple comparison
        // In production, store hashed password and use: Hash::check($request->password, $storedHashedPassword)
        if ($request->password !== $adminPassword) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Generate a simple token (in production, use Laravel Sanctum or Passport)
        $token = Str::random(60);

        // Store token in database or cache (simplified for demo)
        // In production, use proper token management

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => 1,
                'name' => 'Admin User',
                'email' => $adminEmail,
                'role' => 'admin',
            ],
        ]);
    }

    /**
     * Get authenticated admin user
     */
    public function me(Request $request)
    {
        // Get token from header
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // In production, verify token properly
        // For demo, we'll just check if token exists in localStorage
        // This is a simplified version - use proper JWT or Sanctum tokens

        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');

        return response()->json([
            'success' => true,
            'user' => [
                'id' => 1,
                'name' => 'Admin User',
                'email' => $adminEmail,
                'role' => 'admin',
            ],
        ]);
    }

    /**
     * Admin logout
     */
    public function logout(Request $request)
    {
        // In production, invalidate the token
        // For demo, client will just remove token from localStorage

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}

