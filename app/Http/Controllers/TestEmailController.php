<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestEmailController extends Controller
{
    /**
     * Test email sending functionality
     * This is a temporary route for testing Gmail SMTP configuration
     */
    public function testEmail(Request $request)
    {
        try {
            $toEmail = $request->input('email', config('mail.from.address'));
            
            // Send a simple test email
            Mail::raw('This is a test email from AJ Creative Studio. Your Gmail SMTP configuration is working correctly!', function ($message) use ($toEmail) {
                $message->to($toEmail)
                        ->subject('Test Email - Gmail SMTP Configuration');
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully! Please check your inbox (and spam folder).',
                'sent_to' => $toEmail,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Email test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email.',
                'error' => $e->getMessage(),
                'hint' => 'Please check your .env MAIL configuration settings.',
            ], 500);
        }
    }
}

