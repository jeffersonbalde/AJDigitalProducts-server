<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationOtpMail;
use App\Models\Customer;
use App\Models\CustomerTimeLog;
use App\Models\EmailVerificationOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory as FirebaseFactory;

class SignupController extends Controller
{
    private function firebaseFactory(): FirebaseFactory
    {
        $factory = new FirebaseFactory;

        $credentialsJsonB64 = (string) config('services.firebase.credentials_json_b64', '');
        if ($credentialsJsonB64 !== '') {
            $decoded = base64_decode($credentialsJsonB64, true);
            if ($decoded === false) {
                throw new \RuntimeException('Invalid FIREBASE_CREDENTIALS_JSON_B64 (base64 decode failed).');
            }

            $serviceAccount = json_decode($decoded, true);
            if (! is_array($serviceAccount)) {
                throw new \RuntimeException('Invalid FIREBASE_CREDENTIALS_JSON_B64 (decoded JSON is not an object).');
            }

            return $factory->withServiceAccount($serviceAccount);
        }

        $credentialsJson = (string) config('services.firebase.credentials_json', '');
        if ($credentialsJson !== '') {
            $serviceAccount = json_decode($credentialsJson, true);
            if (! is_array($serviceAccount)) {
                throw new \RuntimeException('Invalid FIREBASE_CREDENTIALS_JSON (JSON is not an object).');
            }

            return $factory->withServiceAccount($serviceAccount);
        }

        $credentialsPath = (string) (config('services.firebase.credentials_path')
            ?: storage_path('aj-creative-studio-firebase-adminsdk-fbsvc-8524ea9999.json'));

        if (! file_exists($credentialsPath)) {
            throw new \RuntimeException('Firebase credentials file not found: '.$credentialsPath);
        }

        return $factory->withServiceAccount($credentialsPath);
    }

    private function isCustomerVerified(?Customer $customer): bool
    {
        if (! $customer) {
            return false;
        }

        $status = strtolower((string) ($customer->register_status ?? ''));

        // A customer should be treated as fully registered only when their
        // register_status is explicitly "verified" AND the account is active.
        return $status === 'verified'
            && (bool) ($customer->is_active ?? false);
    }

    /**
     * Handle user registration
     */
    public function signup(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|regex:/[a-zA-Z]/|regex:/[0-9]/',
        ], [
            'password.min' => 'Password must be at least 8 characters long.',
            'password.regex' => 'Password must contain at least one letter and one number.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check if email already exists and is verified
            $normalizedEmail = strtolower(trim((string) $request->email));
            $existingCustomer = Customer::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            if ($this->isCustomerVerified($existingCustomer)) {
                return response()->json([
                    'success' => false,
                    'code' => 'EMAIL_ALREADY_REGISTERED',
                    'message' => 'This email is already registered. Please log in instead.',
                ], 409);
            }

            // If customer exists but not verified, delete old OTPs and resend
            if ($existingCustomer && ! $this->isCustomerVerified($existingCustomer)) {
                // Delete old OTPs
                EmailVerificationOtp::where('customer_id', $existingCustomer->id)->delete();
                
                // Update customer info
                $existingCustomer->update([
                    'name' => $request->name,
                    'email' => $normalizedEmail,
                    'password' => Hash::make($request->password),
                    'register_status' => 'pending',
                ]);
                
                $customer = $existingCustomer;
            } else {
                // Create new customer
                $customer = Customer::create([
                    'name' => $request->name,
                    'email' => $normalizedEmail,
                    'password' => Hash::make($request->password),
                    'is_active' => false,
                    'register_status' => 'pending',
                    'email_verified_at' => null,
                ]);
            }

            // Generate and send OTP
            $otp = $this->generateAndSendOtp($customer);

            if (! $otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification email. Please try again.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please check your email for the verification code.',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Signup error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during registration. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify email with OTP
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $normalizedEmail = strtolower(trim((string) $request->email));
            $customer = Customer::whereRaw('TRIM(LOWER(email)) = ?', [$normalizedEmail])->first();

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found.',
                ], 404);
            }

            if ($this->isCustomerVerified($customer)) {
                return response()->json([
                    'success' => false,
                    'code' => 'EMAIL_ALREADY_VERIFIED',
                    'message' => 'Email is already verified. Please log in.',
                ], 409);
            }

            // Find valid OTP
            $otpRecord = EmailVerificationOtp::where('customer_id', $customer->id)
                ->where('otp', $request->otp)
                ->where('is_used', false)
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            if (! $otpRecord) {
                // Increment OTP attempts
                $customer->increment('otp_attempts');

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code. Please try again or request a new code.',
                ], 422);
            }

            // Verify email
            $customer->update([
                'email_verified_at' => now(),
                'is_active' => true,
                'register_status' => 'verified',
                'otp_attempts' => 0,
            ]);

            // Mark OTP as used
            $otpRecord->markAsUsed();

            // Delete all OTPs for this customer
            EmailVerificationOtp::where('customer_id', $customer->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully! You can now login.',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'email_verified_at' => $customer->email_verified_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Email verification error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during verification. Please try again.',
            ], 500);
        }
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $normalizedEmail = strtolower(trim((string) $request->email));
            $customer = Customer::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found.',
                ], 404);
            }

            if ($this->isCustomerVerified($customer)) {
                return response()->json([
                    'success' => false,
                    'code' => 'EMAIL_ALREADY_VERIFIED',
                    'message' => 'Email is already verified. Please log in.',
                ], 409);
            }

            // Check rate limiting (max 3 requests per hour)
            $lastOtpSent = $customer->otp_sent_at;
            if ($lastOtpSent && $lastOtpSent->diffInMinutes(now()) < 60) {
                $otpCount = EmailVerificationOtp::where('customer_id', $customer->id)
                    ->where('created_at', '>', now()->subHour())
                    ->count();

                if ($otpCount >= 3) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many requests. Please wait before requesting a new code.',
                    ], 429);
                }
            }

            // Delete old unused OTPs
            EmailVerificationOtp::where('customer_id', $customer->id)
                ->where('is_used', false)
                ->delete();

            // Generate and send new OTP
            $otp = $this->generateAndSendOtp($customer);

            if (! $otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification email. Please try again.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'New verification code sent to your email.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Resend OTP error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.',
            ], 500);
        }
    }

    /**
     * Register a customer using Google ID token (Google Identity Services).
     */
    public function googleSignup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $googleClientId = (string) config('services.google.client_id');

        if ($googleClientId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Google auth is not configured on the server.',
                'code' => 'GOOGLE_NOT_CONFIGURED',
            ], 500);
        }

        try {
            $idToken = (string) $request->input('id_token');

            $tokenInfoResponse = Http::timeout(5)
                ->acceptJson()
                ->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $idToken,
                ]);

            if (! $tokenInfoResponse->ok()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token.',
                    'code' => 'GOOGLE_INVALID_TOKEN',
                ], 401);
            }

            $tokenInfo = $tokenInfoResponse->json();
            $audience = (string) ($tokenInfo['aud'] ?? '');
            $email = strtolower(trim((string) ($tokenInfo['email'] ?? '')));
            $emailVerified = (string) ($tokenInfo['email_verified'] ?? '');
            $googleSub = (string) ($tokenInfo['sub'] ?? '');

            if ($audience !== $googleClientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google token audience mismatch.',
                    'code' => 'GOOGLE_AUDIENCE_MISMATCH',
                ], 401);
            }

            if ($email === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Google account did not provide an email.',
                    'code' => 'GOOGLE_NO_EMAIL',
                ], 422);
            }

            if ($emailVerified !== 'true') {
                return response()->json([
                    'success' => false,
                    'message' => 'Google email is not verified.',
                    'code' => 'GOOGLE_EMAIL_NOT_VERIFIED',
                ], 401);
            }

            $name = trim((string) ($tokenInfo['name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($tokenInfo['given_name'] ?? ''));
            }
            if ($name === '') {
                $name = 'Google User';
            }

            $existingCustomer = Customer::whereRaw('LOWER(email) = ?', [$email])->first();

            if ($this->isCustomerVerified($existingCustomer)) {
                return response()->json([
                    'success' => false,
                    'code' => 'EMAIL_ALREADY_REGISTERED',
                    'message' => 'This email is already registered. Please log in instead.',
                ], 409);
            }

            if ($existingCustomer) {
                EmailVerificationOtp::where('customer_id', $existingCustomer->id)->delete();

                $existingCustomer->update([
                    'name' => $name,
                    'email' => $email,
                    'google_sub' => $googleSub !== '' ? $googleSub : $existingCustomer->google_sub,
                    'password' => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'register_status' => 'verified',
                    'otp_attempts' => 0,
                ]);

                $customer = $existingCustomer;
            } else {
                $customer = Customer::create([
                    'name' => $name,
                    'email' => $email,
                    'google_sub' => $googleSub !== '' ? $googleSub : null,
                    'password' => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'register_status' => 'verified',
                    'otp_attempts' => 0,
                    'otp_sent_at' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Google registration successful.',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'email_verified_at' => $customer->email_verified_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Google signup error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during Google registration. Please try again.',
            ], 500);
        }
    }

    /**
     * Register a customer using Firebase ID token.
     */
    public function firebaseSignup(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
            'email' => 'nullable|email',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            try {
                $firebase = $this->firebaseFactory();
            } catch (\Throwable $e) {
                Log::error('Firebase credentials not configured: '.$e->getMessage());
            
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase is not configured on the server.',
                    'code' => 'FIREBASE_NOT_CONFIGURED',
                ], 500);
            }

            $auth = $firebase->createAuth();

            $idToken = (string) $request->input('id_token');
            
            // Verify the ID token
            $verifiedToken = $auth->verifyIdToken($idToken);
            $uid = $verifiedToken->claims()->get('sub');
            $email = strtolower(trim((string) ($verifiedToken->claims()->get('email') ?? $request->input('email') ?? '')));
            $emailVerified = $verifiedToken->claims()->get('email_verified', false);
            $name = trim((string) ($verifiedToken->claims()->get('name') ?? $request->input('name') ?? ''));
            
            if ($name === '') {
                $name = trim((string) ($verifiedToken->claims()->get('given_name') ?? ''));
            }
            if ($name === '') {
                $name = 'Google User';
            }

            if ($email === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Google account did not provide an email.',
                    'code' => 'GOOGLE_NO_EMAIL',
                ], 422);
            }

            if (! $emailVerified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google email is not verified.',
                    'code' => 'GOOGLE_EMAIL_NOT_VERIFIED',
                ], 401);
            }

            $existingCustomer = Customer::whereRaw('LOWER(email) = ?', [$email])->first();

            if ($this->isCustomerVerified($existingCustomer)) {
                return response()->json([
                    'success' => false,
                    'code' => 'EMAIL_ALREADY_REGISTERED',
                    'message' => 'This email is already registered. Please log in instead.',
                ], 409);
            }

            if ($existingCustomer) {
                EmailVerificationOtp::where('customer_id', $existingCustomer->id)->delete();

                $existingCustomer->update([
                    'name' => $name,
                    'email' => $email,
                    'google_sub' => $uid ?? $existingCustomer->google_sub,
                    'password' => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'register_status' => 'verified',
                    'otp_attempts' => 0,
                ]);

                $customer = $existingCustomer;
            } else {
                $customer = Customer::create([
                    'name' => $name,
                    'email' => $email,
                    'google_sub' => $uid ?? null,
                    'password' => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'register_status' => 'verified',
                    'otp_attempts' => 0,
                    'otp_sent_at' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Google registration successful.',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'email_verified_at' => $customer->email_verified_at,
                ],
            ], 201);
        } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
            Log::error('Firebase token verification failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Invalid Firebase token.',
                'code' => 'FIREBASE_INVALID_TOKEN',
            ], 401);
        } catch (\Exception $e) {
            Log::error('Firebase signup error: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during Google registration. Please try again.',
            ], 500);
        }
    }

    /**
     * Handle Firebase Google login
     */
    public function firebaseLogin(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
            'email' => 'nullable|email',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            try {
                $firebase = $this->firebaseFactory();
            } catch (\Throwable $e) {
                Log::error('Firebase credentials not configured: '.$e->getMessage());
            
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase is not configured on the server.',
                    'code' => 'FIREBASE_NOT_CONFIGURED',
                ], 500);
            }

            $auth = $firebase->createAuth();

            $idToken = (string) $request->input('id_token');
            
            // Verify the ID token
            $verifiedToken = $auth->verifyIdToken($idToken);
            $uid = $verifiedToken->claims()->get('sub');
            $email = strtolower(trim((string) ($verifiedToken->claims()->get('email') ?? $request->input('email') ?? '')));
            $emailVerified = $verifiedToken->claims()->get('email_verified', false);
            
            if ($email === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Google account did not provide an email.',
                    'code' => 'GOOGLE_NO_EMAIL',
                ], 422);
            }

            if (! $emailVerified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google email is not verified.',
                    'code' => 'GOOGLE_EMAIL_NOT_VERIFIED',
                ], 401);
            }

            // Find customer by email
            $customer = Customer::whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this email. Please sign up first.',
                    'code' => 'USER_NOT_FOUND',
                ], 404);
            }

            // Check account status BEFORE checking Google auth
            // If status is 'pending', treat it as account not found (user should sign up)
            $registerStatus = strtolower(trim((string) ($customer->register_status ?? '')));
            if ($registerStatus === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this email address. Would you like to sign up instead?',
                    'code' => 'USER_NOT_FOUND',
                ], 404);
            }

            // Check if customer has Google auth (google_sub must match)
            if (empty($customer->google_sub)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account was not created with Google. Please use email and password to sign in.',
                    'code' => 'NOT_GOOGLE_ACCOUNT',
                ], 401);
            }

            // Verify the Firebase UID matches the stored google_sub
            if ($customer->google_sub !== $uid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google account mismatch. Please use the correct Google account.',
                    'code' => 'GOOGLE_ACCOUNT_MISMATCH',
                ], 401);
            }

            // Check account status
            $registerStatus = strtolower(trim((string) ($customer->register_status ?? '')));
            
            if ($registerStatus === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this email address. Please sign up to create an account.',
                    'code' => 'USER_NOT_FOUND',
                ], 404);
            }

            if ($registerStatus !== 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email address before logging in.',
                    'code' => 'EMAIL_NOT_VERIFIED',
                ], 403);
            }

            if (! $customer->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email address before logging in.',
                    'code' => 'EMAIL_NOT_VERIFIED',
                ], 403);
            }

            if (! $customer->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not active. Please contact support.',
                    'code' => 'ACCOUNT_INACTIVE',
                ], 403);
            }

            // Update customer info if needed (name might have changed)
            $name = trim((string) ($verifiedToken->claims()->get('name') ?? $request->input('name') ?? $customer->name));
            if ($name !== '' && $name !== $customer->name) {
                $customer->update(['name' => $name]);
            }

            // Create Sanctum token with 24-hour expiration
            $expiresAt = now()->addHours(24);
            $token = $customer->createToken(
                'auth-token',
                ['*'],
                $expiresAt
            )->plainTextToken;

            // Log login time (gracefully handle errors - don't break login if logging fails)
            try {
                CustomerTimeLog::create([
                    'customer_id' => $customer->id,
                    'action' => 'login',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'logged_at' => now(),
                ]);
            } catch (\Exception $logError) {
                // Log the error but don't fail the login
                Log::warning('Failed to log customer login time: '.$logError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'email_verified_at' => $customer->email_verified_at,
                ],
            ], 200);
        } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
            Log::error('Firebase token verification failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Invalid Firebase token.',
                'code' => 'FIREBASE_INVALID_TOKEN',
            ], 401);
        } catch (\Exception $e) {
            Log::error('Firebase login error: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during Google login. Please try again.',
            ], 500);
        }
    }

    /**
     * Get authenticated customer
     */
    public function me(Request $request)
    {
        // Check if user is authenticated via Sanctum
        $customer = $request->user();

        if (! $customer || ! ($customer instanceof Customer)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'email_verified_at' => $customer->email_verified_at,
                'is_active' => $customer->is_active,
                'register_status' => $customer->register_status,
            ],
        ]);
    }

    /**
     * Handle customer login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $normalizedEmail = strtolower(trim((string) $request->email));
            $customer = Customer::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            // Check if customer exists
            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this email address. Please sign up to create an account.',
                    'code' => 'USER_NOT_FOUND',
                ], 404);
            }

            // Check account status BEFORE password validation to avoid revealing password correctness
            $registerStatus = strtolower(trim((string) ($customer->register_status ?? '')));

            // CASE A: register_status is explicitly 'pending' → behave like account not found
            if ($registerStatus === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this email address. Please sign up to create an account.',
                    'code' => 'USER_NOT_FOUND',
                ], 404);
            }

            // CASE B: any other non-verified status → account exists but not verified
            if ($registerStatus !== 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email address before logging in. Check your inbox for the verification code.',
                    'code' => 'EMAIL_NOT_VERIFIED',
                ], 403);
            }

            // 2) email_verified_at must be set
            if (! $customer->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email address before logging in. Check your inbox for the verification code.',
                    'code' => 'EMAIL_NOT_VERIFIED',
                ], 403);
            }

            // 3) Account must be active
            if (! $customer->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not active. Please verify your email address to activate your account.',
                    'code' => 'ACCOUNT_INACTIVE',
                ], 403);
            }

            // 4) Check if user signed up with Google (has google_sub)
            // Google signup users have a random password they don't know
            // Best practice: Guide them to use Google sign-in instead
            $hasGoogleAuth = ! empty($customer->google_sub);
            
            // If user has Google auth, they should ONLY use Google sign-in
            if ($hasGoogleAuth) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account was created with Google. Please use "Sign in with Google" to access your account.',
                    'code' => 'USE_GOOGLE_LOGIN',
                ], 401);
            }
            
            // Only check password for regular email/password accounts
            if (! Hash::check($request->password, $customer->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid password. Please check your password and try again.',
                    'code' => 'INVALID_PASSWORD',
                ], 401);
            }

            // Create Sanctum token with 24-hour expiration
            $expiresAt = now()->addHours(24);
            $token = $customer->createToken(
                'auth-token',
                ['*'],
                $expiresAt
            )->plainTextToken;

            // Log login time (gracefully handle errors - don't break login if logging fails)
            try {
                CustomerTimeLog::create([
                    'customer_id' => $customer->id,
                    'action' => 'login',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'logged_at' => now(),
                ]);
            } catch (\Exception $logError) {
                // Log the error but don't fail the login
                Log::warning('Failed to log customer login time: '.$logError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'email_verified_at' => $customer->email_verified_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Login error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login. Please try again.',
            ], 500);
        }
    }

    /**
     * Generate and send OTP
     */
    private function generateAndSendOtp(Customer $customer): ?string
    {
        try {
            // Generate 6-digit OTP
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Create OTP record (expires in 15 minutes)
            EmailVerificationOtp::create([
                'customer_id' => $customer->id,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(15),
                'is_used' => false,
            ]);

            // Update customer's OTP sent timestamp
            $customer->update(['otp_sent_at' => now()]);

            // Send email
            Mail::to($customer->email)->send(new EmailVerificationOtpMail($otp, $customer->name));

            return $otp;

        } catch (\Exception $e) {
            Log::error('Generate OTP error: '.$e->getMessage());

            return null;
        }
    }
}
