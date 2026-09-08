<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    /**
     * Login method that dynamically identifies admin or personnel
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $username = $request->username;
        $password = $request->password;

        // Try to find user in admins table
        $admin = Admin::where('username', $username)->first();
        
        if ($admin) {
            // Check if admin is active
            if (!$admin->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact the administrator.',
                ], 403);
            }

            // Verify password
            if (Hash::check($password, $admin->password)) {
                // Update last login
                $admin->update(['last_login_at' => now()]);

                // Create Sanctum token
                $token = $admin->createToken('auth-token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'token' => $token,
                    'user' => [
                        'id' => $admin->id,
                        'name' => $admin->name ?? 'System Administrator',
                        'username' => $admin->username,
                        'role' => 'admin',
                        'type' => 'admin',
                    ],
                ]);
            }
        }

        // Try to find user in personnel table
        $personnel = Personnel::where('username', $username)->first();
        
        if ($personnel) {
            // Check if personnel is active
            if (!$personnel->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact the administrator.',
                ], 403);
            }

            // Verify password
            if (Hash::check($password, $personnel->password)) {
                // Update last login
                $personnel->update(['last_login_at' => now()]);

                // Create Sanctum token
                $token = $personnel->createToken('auth-token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'token' => $token,
                    'user' => [
                        'id' => $personnel->id,
                        'name' => $personnel->name,
                        'username' => $personnel->username,
                        'position' => $personnel->position,
                        'role' => 'personnel',
                        'type' => 'personnel',
                    ],
                ]);
            }
        }

        // If no user found in either table or password doesn't match
        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials',
        ], 401);
    }

    /**
     * Get authenticated user (admin or personnel)
     */
    public function me(Request $request)
    {
        // Check if user is authenticated via Sanctum
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Determine user type and return appropriate data
        if ($user instanceof Admin) {
            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? 'System Administrator',
                    'username' => $user->username,
                    'role' => 'admin',
                    'type' => 'admin',
                ],
            ]);
        }

        if ($user instanceof Personnel) {
            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'position' => $user->position,
                    'role' => 'personnel',
                    'type' => 'personnel',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'User type not recognized',
        ], 500);
    }

    /**
     * Logout - Revoke current token
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Revoke the current token
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}
