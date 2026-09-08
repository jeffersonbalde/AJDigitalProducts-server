<?php

use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\EmailSubscriberController;
use App\Http\Controllers\HeroSliderImageController;
use App\Http\Controllers\LandingPageSectionController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PayMayaWebhookController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductCollectionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductFileUploadController;
use App\Http\Controllers\ProductFaqController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\TestEmailController;
use App\Http\Controllers\TimeLogController;
use Illuminate\Support\Facades\Route;

Route::post('/payments/gcash/create', [PaymentController::class, 'createGcashPayment']);

// Public site branding (logo + logo text)
Route::get('/branding', [BrandingController::class, 'show']);
Route::get('/branding/logo', [BrandingController::class, 'logo']);

// Serve storage files (fallback if symlink doesn't work on DigitalOcean)
// Handles both local storage and S3/Spaces
Route::get('/storage/{path}', function ($path) {
    $path = urldecode($path);
    $disk = config('products.storage_disk', 'public');
    
    // Prevent path traversal
    if (str_contains($path, '..') || empty($path)) {
        abort(404);
    }
    
    $storage = \Illuminate\Support\Facades\Storage::disk($disk);
    
    // Check if file exists in storage
    if (!$storage->exists($path)) {
        abort(404);
    }
    
    // For S3/Spaces, redirect to the CDN URL (more efficient than streaming through Laravel)
    if ($disk === 's3') {
        // Use AWS_URL (CDN endpoint) - read directly from env to avoid config cache issues
        $cdnUrl = env('AWS_URL') ?: config('filesystems.disks.s3.url');
        if ($cdnUrl) {
            // Ensure CDN URL doesn't end with slash
            $cdnUrl = rtrim($cdnUrl, '/');
            // Ensure path doesn't start with slash
            $cleanPath = ltrim($path, '/');
            $url = $cdnUrl . '/' . $cleanPath;
        } else {
            // Fallback: construct CDN URL from bucket and region if AWS_URL not set
            $bucket = env('AWS_BUCKET') ?: config('filesystems.disks.s3.bucket');
            $region = env('AWS_DEFAULT_REGION') ?: config('filesystems.disks.s3.region');
            if ($bucket && $region) {
                $url = "https://{$bucket}.{$region}.cdn.digitaloceanspaces.com/" . ltrim($path, '/');
            } else {
        $url = $storage->url($path);
            }
        }
        // Use 307 (temporary redirect) instead of 302 to prevent browser caching old redirects
        return redirect($url, 307, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
    
    // For local storage, stream the file
    return $storage->response($path, null, [
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->where('path', '.*');

// Order routes (public for creation, auth required for viewing)
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::get('/orders/number/{orderNumber}', [OrderController::class, 'showByOrderNumber']);

// PayMaya checkout endpoint (public, no auth required)
Route::post('/payments/paymaya/checkout', [PaymentController::class, 'createPayMayaCheckout']);

// PayMaya webhook endpoint (must be public, no auth required)
// PayMaya will call this endpoint to notify us of payment status changes
Route::post('/webhooks/paymaya', [PayMayaWebhookController::class, 'handle']);

// Public product catalogue (no auth required)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}/faqs', [ProductFaqController::class, 'indexByProduct']);

// Public product collections with products (for storefront filters)
Route::get('/public/product-collections', [ProductCollectionController::class, 'publicListWithProducts']);
Route::get('/product-collections/slug/{slug}', [ProductCollectionController::class, 'showBySlug']);
Route::get('/products/{product}/reviews', [ProductReviewController::class, 'indexByProduct']);

// Public route for active landing page sections
Route::get('/landing-page-sections/active', [LandingPageSectionController::class, 'active']);
Route::post('/contact-messages', [ContactMessageController::class, 'store']);

// Public email subscribe endpoint
Route::post('/email-subscribers', [EmailSubscriberController::class, 'store']);
Route::post('/product-reviews', [ProductReviewController::class, 'store']);

// Public download routes (token-based, no auth required)
Route::get('/downloads/{token}', [DownloadController::class, 'download']);
Route::get('/downloads/{token}/info', [DownloadController::class, 'info']);

// Login routes (replaces AdminController login)
Route::post('/admin/login', [LoginController::class, 'login']);

// Test email route (remove in production)
Route::post('/test-email', [TestEmailController::class, 'testEmail']);

// Public auth routes
Route::post('/auth/signup', [SignupController::class, 'signup']);
Route::post('/auth/login', [SignupController::class, 'login']);
Route::post('/auth/verify-email', [SignupController::class, 'verifyEmail']);
Route::post('/auth/resend-otp', [SignupController::class, 'resendOtp']);
Route::post('/auth/google/signup', [SignupController::class, 'googleSignup']);
Route::post('/auth/firebase/signup', [SignupController::class, 'firebaseSignup']);
Route::post('/auth/firebase/login', [SignupController::class, 'firebaseLogin']);

// Phase 3: Password reset routes
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/me', [LoginController::class, 'me']);
    Route::post('/admin/logout', [LoginController::class, 'logout']);
    Route::put('/admin/change-password', [AdminSettingsController::class, 'changePassword']);
    Route::get('/admin/branding', [AdminSettingsController::class, 'getBranding']);
    Route::post('/admin/branding', [AdminSettingsController::class, 'updateBranding']);

    // Customer authentication routes
    Route::get('/auth/me', [SignupController::class, 'me']);

    // Phase 3: Logout
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Product Management Routes (Admin only)
    Route::get('/products/{id}/download', [ProductController::class, 'downloadFile']);
    Route::get('/products/categories/list', [ProductController::class, 'categories']);
    Route::apiResource('products', ProductController::class)->except(['index']);

    // Chunked product file upload (avoids 413 Content Too Large on platforms with small body limits)
    Route::post('/products/{product}/file-upload/init', [ProductFileUploadController::class, 'init']);
    Route::post('/products/{product}/file-upload/chunk', [ProductFileUploadController::class, 'chunk']);
    Route::post('/products/{product}/file-upload/complete', [ProductFileUploadController::class, 'complete']);

    // Product Category Management Routes
    Route::apiResource('product-categories', ProductCategoryController::class);
    Route::get('/product-categories/list', [ProductCategoryController::class, 'list']);

    // Product Collection Management Routes
    Route::apiResource('product-collections', ProductCollectionController::class);
    Route::get('/product-collections/list', [ProductCollectionController::class, 'list']);
    Route::post('/product-collections/{id}/products', [ProductCollectionController::class, 'addProducts']);
    Route::delete('/product-collections/{id}/products', [ProductCollectionController::class, 'removeProducts']);
    Route::put('/product-collections/{id}/products/order', [ProductCollectionController::class, 'updateProductOrder']);

    // Landing Page Sections Management Routes
    Route::apiResource('landing-page-sections', LandingPageSectionController::class);
    Route::get('/landing-page-sections/active/list', [LandingPageSectionController::class, 'active']);
    Route::get('/landing-page-sections/type/{type}', [LandingPageSectionController::class, 'getByType']);
    Route::put('/landing-page-sections/order/update', [LandingPageSectionController::class, 'updateOrder']);
    Route::put('/landing-page-sections/{id}/publish', [LandingPageSectionController::class, 'publish']);
    Route::put('/landing-page-sections/{id}/unpublish', [LandingPageSectionController::class, 'unpublish']);

    // Hero Slider Image Upload
    Route::post('/hero-slider/upload-image', [HeroSliderImageController::class, 'upload']);

    // Customer Management Routes
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/stats', [CustomerController::class, 'stats']);

    // Customer Time Logging Routes
    Route::get('/customer-time-logs', [TimeLogController::class, 'index']);
    Route::get('/customers/{id}/time-logs', [TimeLogController::class, 'getCustomerLogs']);

    // Order routes (authenticated)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);

    // Product reviews (admin)
    Route::get('/product-reviews', [ProductReviewController::class, 'index']);
    Route::put('/product-reviews/{productReview}', [ProductReviewController::class, 'update']);
    Route::delete('/product-reviews/{productReview}', [ProductReviewController::class, 'destroy']);

    // Product FAQs (admin)
    Route::get('/product-faqs', [ProductFaqController::class, 'index']);
    Route::post('/product-faqs', [ProductFaqController::class, 'store']);
    Route::put('/product-faqs/{productFaq}', [ProductFaqController::class, 'update']);
    Route::delete('/product-faqs/{productFaq}', [ProductFaqController::class, 'destroy']);

    // Customer download routes (for dashboard)
    Route::get('/downloads', [DownloadController::class, 'index']);
    Route::get('/contact-messages', [ContactMessageController::class, 'index']);
    Route::delete('/contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy']);

    // Email subscribers (admin)
    Route::get('/email-subscribers', [EmailSubscriberController::class, 'index']);

    // Customer cart routes (authenticated customers only)
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/save', [CartController::class, 'save']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::put('/cart/update/{productId}', [CartController::class, 'update']);
    Route::delete('/cart/remove/{productId}', [CartController::class, 'remove']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);
    Route::post('/cart/merge', [CartController::class, 'merge']);
});
