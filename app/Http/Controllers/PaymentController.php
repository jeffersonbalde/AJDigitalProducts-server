<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Generate QR Ph (Philippines QR Code Standard) format for GCash
     * This follows the EMV QR Code specification used by GCash
     */
    private function generateQRPh($gcashNumber, $amount, $merchantName, $merchantCity = 'Manila')
    {
        // QR Ph format follows EMV QR Code specification
        $qrString = '';
        
        // Payload Format Indicator (00) - Always "01" for EMV QR
        $qrString .= $this->formatDataObject('00', '01');
        
        // Point of Initiation Method (01) - "12" for dynamic QR (with amount)
        $qrString .= $this->formatDataObject('01', '12');
        
        // Merchant Account Information (26-51)
        // For GCash, we use the mobile number as the account identifier
        $merchantAccountInfo = $this->formatDataObject('00', 'gcash'); // Payment network
        $merchantAccountInfo .= $this->formatDataObject('01', $gcashNumber); // GCash mobile number
        $qrString .= $this->formatDataObject('26', $merchantAccountInfo);
        
        // Merchant Category Code (52) - Use generic code
        $qrString .= $this->formatDataObject('52', '0000');
        
        // Transaction Currency (53) - PHP = 608
        $qrString .= $this->formatDataObject('53', '608');
        
        // Transaction Amount (54) - Format: XX.XX
        $formattedAmount = number_format($amount, 2, '.', '');
        $qrString .= $this->formatDataObject('54', $formattedAmount);
        
        // Country Code (58) - PH
        $qrString .= $this->formatDataObject('58', 'PH');
        
        // Merchant Name (59) - Max 25 characters
        $merchantName = substr($merchantName, 0, 25);
        $qrString .= $this->formatDataObject('59', $merchantName);
        
        // Merchant City (60) - Max 15 characters
        $merchantCity = substr($merchantCity, 0, 15);
        $qrString .= $this->formatDataObject('60', $merchantCity);
        
        // Additional Data Field Template (62) - Optional, can include order reference
        // CRC (63) - Calculate CRC for the data before adding CRC field
        $crc = $this->calculateCRC($qrString . '6304');
        $qrString .= $this->formatDataObject('63', $crc);
        
        return $qrString;
    }
    
    /**
     * Format data object in EMV QR format: ID + Length + Value
     */
    private function formatDataObject($id, $value)
    {
        $length = str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
        return $id . $length . $value;
    }
    
    /**
     * Calculate CRC16 for QR code
     */
    private function calculateCRC($data)
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc <<= 1;
                }
            }
        }
        return strtoupper(str_pad(dechex($crc & 0xFFFF), 4, '0', STR_PAD_LEFT));
    }
    
    /**
     * Create a GCash QR code payment (Plain QR Ph format)
     */
    public function createGcashPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'order_id' => 'nullable|string',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $amount = $request->amount;
            $orderId = $request->order_id ?? 'ORDER-' . uniqid();
            
            // Get GCash merchant details from config
            $gcashNumber = config('services.gcash.merchant_number');
            $merchantName = config('services.gcash.merchant_name', 'Merchant');
            $merchantCity = config('services.gcash.merchant_city', 'Manila');
            
            if (empty($gcashNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'GCash merchant number is not configured. Please set GCASH_MERCHANT_NUMBER in your .env file.',
                ], 400);
            }
            
            // Generate QR Ph format string
            $qrString = $this->generateQRPh($gcashNumber, $amount, $merchantName, $merchantCity);
            
            // Generate a unique payment ID for tracking
            $paymentId = 'PAY-' . uniqid();
            
            return response()->json([
                'success' => true,
                'payment' => [
                    'id' => $paymentId,
                    'qr_string' => $qrString,
                    'reference_id' => $orderId,
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'status' => 'PENDING',
                    'gcash_number' => $gcashNumber,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Payment Creation Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Check payment status
     * Note: Without a payment gateway, you'll need to manually verify payments
     * or implement your own payment verification system
     */
    public function checkPaymentStatus(Request $request, $paymentId)
    {
        // Without a payment gateway, status checking would need to be
        // implemented based on your own payment tracking system
        // For now, we'll return a placeholder response
        
        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $paymentId,
                'status' => 'PENDING', // You'll need to implement your own status tracking
                'message' => 'Please verify payment manually in your GCash account',
            ],
        ]);
    }

    /**
     * Create PayMaya checkout session
     * This creates a checkout session and returns the redirect URL
     */
    public function createPayMayaCheckout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'order_id' => 'nullable|integer|exists:orders,id',
            'order_number' => 'nullable|string|exists:orders,order_number',
            'description' => 'nullable|string|max:255',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url',
            'failure_url' => 'nullable|url',
            'customer' => 'nullable|array',
            'customer.name' => 'nullable|string|max:255',
            'customer.first_name' => 'nullable|string|max:255',
            'customer.last_name' => 'nullable|string|max:255',
            'customer.email' => 'nullable|email|max:255',
            'customer.phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $publicKey = config('services.paymaya.public_key');
            $secretKey = config('services.paymaya.secret_key');
            $environment = config('services.paymaya.environment', 'sandbox');

            if (empty($publicKey) || empty($secretKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'PayMaya credentials are not configured. Please set PAYMAYA_PUBLIC_KEY and PAYMAYA_SECRET_KEY in your .env file.',
                ], 400);
            }

            // Determine API base URL based on environment
            $apiBaseUrl = $environment === 'production' 
                ? 'https://pg.maya.ph'
                : 'https://pg-sandbox.maya.ph';

            $amount = $request->amount;
            
            // Get order if order_id or order_number is provided
            $order = null;
            if ($request->order_id) {
                $order = \App\Models\Order::find($request->order_id);
            } elseif ($request->order_number) {
                $order = \App\Models\Order::where('order_number', $request->order_number)->first();
            }
            
            // If order exists, use its details
            if ($order) {
                // Verify amount matches order total
                if (abs($order->total_amount - $amount) > 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment amount does not match order total',
                    ], 400);
                }
                
                $orderId = $order->order_number;
                $description = $request->description ?? "Order {$order->order_number}";
                
                // Extract customer details from order
                $customerName = $order->guest_name ?? ($order->customer ? $order->customer->name : null);
                $customerEmail = $order->guest_email ?? ($order->customer ? $order->customer->email : null);
                
                // Parse name into first and last name
                $nameParts = $customerName ? explode(' ', $customerName, 2) : ['Customer', ''];
                $firstName = $nameParts[0] ?? 'Customer';
                $lastName = $nameParts[1] ?? '';
            } else {
                $orderId = $request->order_id ?? 'ORDER-' . uniqid();
                $description = $request->description ?? 'Order Payment';
                $firstName = 'Customer';
                $lastName = '';
                $customerEmail = null;
            }
            
            // Override with request customer data if provided (takes priority)
            // Prefer first_name/last_name if provided, otherwise parse from name
            if ($request->has('customer.first_name')) {
                $firstName = $request->input('customer.first_name');
            } elseif ($request->has('customer.name')) {
                $nameParts = explode(' ', $request->input('customer.name'), 2);
                $firstName = $nameParts[0] ?? 'Customer';
            }
            
            if ($request->has('customer.last_name')) {
                $lastName = $request->input('customer.last_name');
            } elseif ($request->has('customer.name') && !$request->has('customer.first_name')) {
                // Only parse if we didn't already set firstName from first_name
                $nameParts = explode(' ', $request->input('customer.name'), 2);
                $lastName = $nameParts[1] ?? '';
            }
            
            if ($request->has('customer.email')) {
                $customerEmail = $request->input('customer.email');
            }
            
            // Build checkout data
            $checkoutData = [
                'totalAmount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => 'PHP',
                ],
                'buyer' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'contact' => [
                        'phone' => $request->input('customer.phone', '+639000000000'),
                        'email' => $customerEmail ?? $request->input('customer.email', 'customer@example.com'),
                    ],
                ],
                'items' => [
                    [
                        'name' => $description,
                        'quantity' => '1',
                        'code' => $orderId,
                        'description' => $description,
                        'amount' => [
                            'value' => number_format($amount, 2, '.', ''),
                            'currency' => 'PHP',
                        ],
                        'totalAmount' => [
                            'value' => number_format($amount, 2, '.', ''),
                            'currency' => 'PHP',
                        ],
                    ],
                ],
                'redirectUrl' => [
                    'success' => $request->success_url,
                    'failure' => $request->failure_url ?? $request->cancel_url,
                    'cancel' => $request->cancel_url,
                ],
                'requestReferenceNumber' => $orderId,
            ];

            // Make API call to PayMaya
            // PayMaya Checkout API uses Basic Auth
            // Format: Base64 encode of "PublicKey:SecretKey"
            $authString = base64_encode($publicKey . ':' . $secretKey);
            
            Log::info('PayMaya API Request Details', [
                'url' => $apiBaseUrl . '/checkout/v1/checkouts',
                'environment' => $environment,
                'public_key_set' => !empty($publicKey),
                'secret_key_set' => !empty($secretKey),
                'public_key_prefix' => substr($publicKey, 0, 10) . '...',
                'secret_key_prefix' => substr($secretKey, 0, 10) . '...',
                'auth_header' => 'Basic ' . substr($authString, 0, 20) . '...',
            ]);
            
            try {
                $response = Http::withBasicAuth($publicKey, $secretKey)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->post($apiBaseUrl . '/checkout/v1/checkouts', $checkoutData);
            } catch (\Exception $e) {
                Log::error('PayMaya HTTP Request Exception', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            $responseData = $response->json();

            Log::info('PayMaya Checkout Created', [
                'order_id' => $orderId,
                'amount' => $amount,
                'response_status' => $response->status(),
                'response_data' => $responseData,
            ]);

            if ($response->successful() && isset($responseData['checkoutId'])) {
                // Use PayMaya's provided redirectUrl, or build it if not provided
                $redirectUrl = $responseData['redirectUrl'] ?? $apiBaseUrl . '/checkout/v1/checkouts/' . $responseData['checkoutId'];

                // Update order with PayMaya checkout ID if order exists
                if ($order) {
                    $order->update([
                        'payment_gateway_id' => $responseData['checkoutId'],
                        'payment_method' => 'paymaya',
                    ]);
                }

                Log::info('PayMaya Checkout Success', [
                    'checkout_id' => $responseData['checkoutId'],
                    'redirect_url' => $redirectUrl,
                    'order_id' => $orderId,
                    'order_db_id' => $order ? $order->id : null,
                ]);

                return response()->json([
                    'success' => true,
                    'checkout' => [
                        'id' => $responseData['checkoutId'],
                        'redirect_url' => $redirectUrl,
                        'order_id' => $orderId,
                        'order_db_id' => $order ? $order->id : null,
                        'amount' => $amount,
                        'currency' => 'PHP',
                    ],
                ]);
            } else {
                $errorMessage = $responseData['message'] ?? $responseData['error'] ?? 'Failed to create checkout session';
                
                Log::error('PayMaya Checkout Error', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                    'response' => $responseData,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'details' => $responseData,
                ], $response->status() ?: 500);
            }
        } catch (\Exception $e) {
            Log::error('PayMaya Checkout Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the checkout session.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

