<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPasswordResetToken;
use App\Models\CustomerTimeLog;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * Request password reset
     * Handles: Regular users, Google users, and unregistered users
     */
    public function forgotPassword(Request $request)
    {
        // Rate limiting: 3 attempts per hour per IP
        $key = 'forgot-password:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => 'Too many password reset requests. Please try again in ' . ceil($seconds / 3600) . ' hour(s).',
                'code' => 'RATE_LIMIT_EXCEEDED',
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($key);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $normalizedEmail = strtolower(trim((string) $request->email));
            $customer = Customer::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            // Scenario 1: Customer not found (unregistered or pending)
            if (!$customer) {
                RateLimiter::hit($key);
                // Return generic success message to prevent email enumeration
                return response()->json([
                    'success' => true,
                    'message' => 'If an account exists with this email, a password reset link has been sent. Please check your inbox.',
                ], 200);
            }

            // Scenario 2: Google signup user (has google_sub)
            if (!empty($customer->google_sub)) {
                RateLimiter::hit($key);
                return response()->json([
                    'success' => false,
                    'message' => 'This account was created with Google. Please use "Sign in with Google" to access your account.',
                    'code' => 'GOOGLE_ACCOUNT',
                    'show_google_login' => true,
                ], 200); // Return 200 but with success: false to trigger special handling
            }

            // Scenario 3: Account not verified or inactive
            $registerStatus = strtolower(trim((string) ($customer->register_status ?? '')));
            if ($registerStatus !== 'verified' || !$customer->email_verified_at || !$customer->is_active) {
                RateLimiter::hit($key);
                // Return generic success message to prevent information disclosure
                return response()->json([
                    'success' => true,
                    'message' => 'If an account exists with this email, a password reset link has been sent. Please check your inbox.',
                ], 200);
            }

            // Scenario 4: Valid email/password customer - Generate reset token
            $token = CustomerPasswordResetToken::generateToken();
            
            // Create reset token record (expires in 1 hour)
            CustomerPasswordResetToken::create([
                'customer_id' => $customer->id,
                'token' => $token,
                'expires_at' => now()->addHour(),
                'is_used' => false,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send password reset email (send immediately, don't queue)
            try {
                Mail::to($customer->email)->send(new PasswordResetMail($token, $customer->name, $customer->email));
                Log::info('Password reset email sent successfully to: ' . $customer->email);
            } catch (\Swift_TransportException $transportError) {
                // SMTP/transport errors
                Log::error('SMTP Error sending password reset email to ' . $customer->email . ': ' . $transportError->getMessage());
                Log::error('SMTP Error details: ' . $transportError->getTraceAsString());
            } catch (\Exception $mailError) {
                // Other email errors
                Log::error('Failed to send password reset email to ' . $customer->email . ': ' . $mailError->getMessage());
                Log::error('Email error trace: ' . $mailError->getTraceAsString());
            }
            // Note: We don't fail the request even if email fails (security best practice)

            RateLimiter::hit($key);

            return response()->json([
                'success' => true,
                'message' => 'If an account exists with this email, a password reset link has been sent. Please check your inbox.',
                // In production, don't return the token. This is for testing only.
                'reset_token' => env('APP_DEBUG') ? $token : null,
            ], 200);

        } catch (\Exception $e) {
            RateLimiter::hit($key);
            Log::error('Forgot password error: ' . $e->getMessage());
            // Always return generic success to prevent information disclosure
            return response()->json([
                'success' => true,
                'message' => 'If an account exists with this email, a password reset link has been sent. Please check your inbox.',
            ], 200);
        }
    }

    /**
     * Reset password using token
     */
    public function resetPassword(Request $request)
    {
        // Rate limiting: 5 attempts per hour per IP
        $key = 'reset-password:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => 'Too many password reset attempts. Please try again in ' . ceil($seconds / 3600) . ' hour(s).',
                'code' => 'RATE_LIMIT_EXCEEDED',
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:64',
            'email' => 'required|email',
            'password' => 'required|string|min:8|regex:/[a-zA-Z]/|regex:/[0-9]/',
        ], [
            'password.min' => 'Password must be at least 8 characters long.',
            'password.regex' => 'Password must contain at least one letter and one number.',
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($key);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $normalizedEmail = strtolower(trim((string) $request->email));
            $customer = Customer::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            if (!$customer) {
                RateLimiter::hit($key);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset token or email.',
                    'code' => 'INVALID_TOKEN',
                ], 400);
            }

            // Find valid reset token
            $resetToken = CustomerPasswordResetToken::where('customer_id', $customer->id)
                ->where('token', $request->token)
                ->where('is_used', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$resetToken) {
                RateLimiter::hit($key);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token.',
                    'code' => 'INVALID_TOKEN',
                ], 400);
            }

            // Update password
            $customer->update([
                'password' => Hash::make($request->password),
            ]);

            // Mark token as used
            $resetToken->markAsUsed();

            // Revoke all existing tokens (force re-login)
            $customer->tokens()->delete();

            RateLimiter::clear($key);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully. Please login with your new password.',
            ], 200);

        } catch (\Exception $e) {
            RateLimiter::hit($key);
            Log::error('Reset password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during password reset. Please try again.',
            ], 500);
        }
    }

    /**
     * Logout - Revoke current token
     */
    public function logout(Request $request)
    {
        $customer = $request->user();

        if ($customer) {
            // Revoke the current token
            $request->user()->currentAccessToken()->delete();

            // Log logout time (gracefully handle errors)
            try {
                CustomerTimeLog::create([
                    'customer_id' => $customer->id,
                    'action' => 'logout',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'logged_at' => now(),
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to log customer logout time: ' . $logError->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}
