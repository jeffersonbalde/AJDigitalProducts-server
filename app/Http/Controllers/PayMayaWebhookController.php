<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductDownload;
use App\Models\CustomerCart;
use App\Mail\OrderConfirmationMail;

class PayMayaWebhookController extends Controller
{
    /**
     * Handle PayMaya webhook callbacks
     * This endpoint receives payment status updates from PayMaya
     */
    public function handle(Request $request)
    {
        // Log the incoming webhook for debugging
        Log::info('PayMaya Webhook Received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        // Validate webhook signature (important for security)
        // PayMaya sends a signature in the headers that we should verify
        $signature = $request->header('X-PayMaya-Signature');
        $webhookSecret = config('services.paymaya.webhook_secret');
        
        // Only verify signature if webhook secret is configured
        // In sandbox mode, PayMaya may not require signature verification
        if ($webhookSecret && $signature) {
            if (!$this->verifySignature($request, $signature, $webhookSecret)) {
                Log::warning('PayMaya Webhook: Invalid signature', [
                    'received_signature' => $signature,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature',
                ], 401);
            }
        } else {
            // Log if signature verification is skipped (for debugging)
            if (!$webhookSecret) {
                Log::info('PayMaya Webhook: Signature verification skipped (no webhook secret configured)');
            }
        }

        // PayMaya sends webhooks in different formats
        // Format 1: { id, type, data } - Standard format
        // Format 2: { id, status, isPaid, amount, ... } - Direct payment status format
        // Format 3: { id, status, paymentStatus, requestReferenceNumber, ... } - Checkout completion format
        
        $webhookBody = $request->all();
        
        // Check if it's the checkout completion format (Format 3)
        // This format has status and paymentStatus fields (but no isPaid)
        if (isset($webhookBody['status']) && isset($webhookBody['paymentStatus']) && !isset($webhookBody['isPaid'])) {
            $status = $webhookBody['status'];
            $paymentStatus = $webhookBody['paymentStatus'];
            $requestReferenceNumber = $webhookBody['requestReferenceNumber'] ?? null;
            
            Log::info('PayMaya Webhook: Checkout completion format', [
                'status' => $status,
                'paymentStatus' => $paymentStatus,
                'requestReferenceNumber' => $requestReferenceNumber,
            ]);
            
            try {
                // Handle different checkout statuses
                if ($paymentStatus === 'PAYMENT_SUCCESS' && ($status === 'COMPLETED' || $status === 'PAYMENT_SUCCESS')) {
                    // Payment was successful - find order and mark as paid
                    if ($requestReferenceNumber) {
                        $order = \App\Models\Order::where('order_number', $requestReferenceNumber)->first();
                        if ($order && $order->payment_status !== 'paid') {
                            $paymentId = $webhookBody['id'] ?? null;
                            $order->markAsPaid($paymentId);
                            Log::info('Order updated to paid from checkout completion webhook', [
                                'order_number' => $order->order_number,
                                'payment_id' => $paymentId,
                            ]);

                            // Clear backend cart for authenticated customer so items don't reappear
                            if ($order->customer_id) {
                                CustomerCart::where('customer_id', $order->customer_id)->delete();
                            }
                            
                            // Generate download tokens for each order item (Phase 2: Instant Digital Delivery)
                            $this->generateDownloadTokens($order);
                            
                            // Send order confirmation email with Excel attachment (Phase 4: Email Delivery)
                            $this->sendOrderConfirmationEmail($order);
                        }
                    }
                } elseif ($paymentStatus === 'PAYMENT_EXPIRED' || $status === 'EXPIRED') {
                    // Checkout expired - mark order as cancelled if not already paid
                    if ($requestReferenceNumber) {
                        $order = \App\Models\Order::where('order_number', $requestReferenceNumber)->first();
                        if ($order && $order->payment_status === 'pending') {
                            $order->markAsCancelled();
                            Log::info('Order cancelled due to expired checkout', [
                                'order_number' => $order->order_number,
                            ]);
                        }
                    }
                } elseif ($paymentStatus === 'PAYMENT_FAILED') {
                    // Payment failed
                    if ($requestReferenceNumber) {
                        $order = \App\Models\Order::where('order_number', $requestReferenceNumber)->first();
                        if ($order) {
                            $order->markAsFailed();
                            Log::info('Order marked as failed from checkout completion webhook', [
                                'order_number' => $order->order_number,
                            ]);
                        }
                    }
                } else {
                    Log::info('PayMaya Webhook: Unhandled checkout status', [
                        'status' => $status,
                        'paymentStatus' => $paymentStatus,
                        'body' => $webhookBody,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('PayMaya Webhook: Error processing checkout completion format', [
                    'error' => $e->getMessage(),
                    'body' => $webhookBody,
                ]);
            }
            
            // Always return 200 to acknowledge receipt
            return response()->json([
                'success' => true,
                'message' => 'Webhook received',
            ], 200);
        }
        
        // Check if it's the direct payment status format (Format 2)
        if (isset($webhookBody['status']) && isset($webhookBody['isPaid'])) {
            // This is a direct payment status webhook
            $status = $webhookBody['status'];
            $isPaid = $webhookBody['isPaid'] ?? false;
            
            Log::info('PayMaya Webhook: Direct payment status format', [
                'status' => $status,
                'isPaid' => $isPaid,
                'amount' => $webhookBody['amount'] ?? null,
                'checkoutId' => $webhookBody['checkoutId'] ?? null,
            ]);
            
            try {
                if ($isPaid && $status === 'PAYMENT_SUCCESS') {
                    $this->handlePaymentSuccess($webhookBody);
                } elseif ($status === 'PAYMENT_FAILED') {
                    $this->handlePaymentFailed($webhookBody);
                } elseif ($status === 'PAYMENT_CANCELLED' || $status === 'PAYMENT_CANCELED') {
                    $this->handlePaymentCancelled($webhookBody);
                } elseif ($status === 'PAYMENT_PENDING') {
                    $this->handlePaymentPending($webhookBody);
                } else {
                    Log::info('PayMaya Webhook: Unhandled payment status', [
                        'status' => $status,
                        'isPaid' => $isPaid,
                        'body' => $webhookBody,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('PayMaya Webhook: Error processing direct format', [
                    'error' => $e->getMessage(),
                    'body' => $webhookBody,
                ]);
            }
            
            // Always return 200 to acknowledge receipt
            return response()->json([
                'success' => true,
                'message' => 'Webhook received',
            ], 200);
        }
        
        // Otherwise, try the standard format (Format 1)
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
            'type' => 'required|string',
            'data' => 'required|array',
        ]);

        if ($validator->fails()) {
            Log::warning('PayMaya Webhook: Validation failed', [
                'errors' => $validator->errors(),
                'body' => $webhookBody,
            ]);
            
            // Still return 200 to prevent PayMaya from retrying
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook payload format',
            ], 200);
        }

        $webhookId = $request->input('id');
        $webhookType = $request->input('type');
        $webhookData = $request->input('data');

        // Handle different webhook event types
        try {
            // If we've already sent the confirmation email, don't send again
            if ($order->confirmation_email_sent_at) {
                Log::info('Order confirmation email already sent (flag on order)', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'confirmation_email_sent_at' => $order->confirmation_email_sent_at,
                ]);

                return;
            }
            switch ($webhookType) {
                case 'payment.success':
                case 'payment.paid':
                    $this->handlePaymentSuccess($webhookData);
                    break;
                    
                case 'payment.failed':
                    $this->handlePaymentFailed($webhookData);
                    break;
                    
                case 'payment.cancelled':
                case 'payment.canceled':
                    $this->handlePaymentCancelled($webhookData);
                    break;
                    
                case 'payment.pending':
                    $this->handlePaymentPending($webhookData);
                    break;
                    
                default:
                    Log::info('PayMaya Webhook: Unhandled event type', [
                        'type' => $webhookType,
                        'data' => $webhookData,
                    ]);
            }

            // Always return 200 to acknowledge receipt
            return response()->json([
                'success' => true,
                'message' => 'Webhook received',
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('PayMaya Webhook: Processing error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'webhook_type' => $webhookType,
                'webhook_data' => $webhookData,
            ]);

            // Still return 200 to prevent PayMaya from retrying
            // But log the error for manual investigation
            return response()->json([
                'success' => false,
                'message' => 'Error processing webhook',
            ], 200);
        }
    }

    /**
     * Verify webhook signature from PayMaya
     * This ensures the webhook is actually from PayMaya and hasn't been tampered with
     */
    private function verifySignature(Request $request, $signature, $secret)
    {
        // PayMaya typically uses HMAC SHA256 for webhook signatures
        // The signature is usually in the format: hash=signature_value
        // You'll need to check PayMaya's documentation for their exact signature format
        
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        // Compare signatures (use hash_equals to prevent timing attacks)
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSuccess($data)
    {
        Log::info('PayMaya Payment Success', ['data' => $data]);
        
        // Extract payment information
        $paymentId = $data['id'] ?? $data['paymentId'] ?? null;
        $checkoutId = $data['checkoutId'] ?? null;
        $requestReferenceNumber = $data['requestReferenceNumber'] ?? null;
        
        // Extract customer information from PayMaya (if available)
        $customerEmail = $data['buyer']['contact']['email'] ?? $data['customer']['email'] ?? $data['email'] ?? null;
        $customerName = $data['buyer']['firstName'] ?? $data['customer']['name'] ?? $data['name'] ?? null;
        $amount = $data['amount'] ?? null;
        $status = $data['status'] ?? null;
        
        // Find order by checkout ID or order number
        $order = null;
        if ($checkoutId) {
            $order = \App\Models\Order::where('payment_gateway_id', $checkoutId)->first();
        }
        
        // If not found by checkout ID, try by order number from requestReferenceNumber
        if (!$order && $requestReferenceNumber) {
            $order = \App\Models\Order::where('order_number', $requestReferenceNumber)->first();
        }
        
        if ($order) {
            // Update customer information from PayMaya if order was guest order
            if (!$order->customer_id && ($customerEmail || $customerName)) {
                $updateData = [];
                if ($customerEmail && (!$order->guest_email || $order->guest_email === 'pending@payment.com')) {
                    $updateData['guest_email'] = $customerEmail;
                }
                if ($customerName && (!$order->guest_name || $order->guest_name === 'Pending Payment')) {
                    $updateData['guest_name'] = $customerName;
                }
                if (!empty($updateData)) {
                    $order->update($updateData);
                    Log::info('PayMaya Webhook: Updated guest order with customer info', $updateData);
                }
            }
            
            $order->markAsPaid($paymentId);
            
            Log::info('Order updated to paid', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $paymentId,
            ]);

            // Clear backend cart for authenticated customer so items don't reappear
            if ($order->customer_id) {
                CustomerCart::where('customer_id', $order->customer_id)->delete();
            }

            // Generate download tokens for each order item (Phase 2: Instant Digital Delivery)
            $this->generateDownloadTokens($order);
            
            // Send order confirmation email with Excel attachment (Phase 4: Email Delivery)
            $this->sendOrderConfirmationEmail($order);
        } else {
            Log::warning('Order not found for payment success', [
                'checkout_id' => $checkoutId,
                'request_reference_number' => $requestReferenceNumber,
            ]);
        }
        
        // TODO: Send confirmation email to customer (Phase 4)
        // TODO: Update inventory if needed
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($data)
    {
        Log::warning('PayMaya Payment Failed', ['data' => $data]);
        
        $paymentId = $data['id'] ?? $data['paymentId'] ?? null;
        $checkoutId = $data['checkoutId'] ?? null;
        $requestReferenceNumber = $data['requestReferenceNumber'] ?? null;
        $amount = $data['amount'] ?? null;
        $errorCode = $data['errorCode'] ?? null;
        $errorMessage = $data['errorMessage'] ?? $data['failureReason'] ?? 'Payment failed';
        
        Log::error('PayMaya Payment Failed Details', [
            'payment_id' => $paymentId,
            'checkout_id' => $checkoutId,
            'order_id' => $requestReferenceNumber,
            'amount' => $amount,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
        
        // Find order by checkout ID or order number
        $order = null;
        if ($checkoutId) {
            $order = \App\Models\Order::where('payment_gateway_id', $checkoutId)->first();
        }
        
        if (!$order && $requestReferenceNumber) {
            $order = \App\Models\Order::where('order_number', $requestReferenceNumber)->first();
        }
        
        if ($order) {
            $order->markAsFailed();
            
            Log::info('Order updated to failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error_message' => $errorMessage,
            ]);
        } else {
            Log::warning('Order not found for payment failure', [
                'checkout_id' => $checkoutId,
                'request_reference_number' => $requestReferenceNumber,
            ]);
        }
    }

    /**
     * Handle cancelled payment
     */
    private function handlePaymentCancelled($data)
    {
        Log::info('PayMaya Payment Cancelled', ['data' => $data]);
        
        $checkoutId = $data['checkoutId'] ?? null;
        $requestReferenceNumber = $data['requestReferenceNumber'] ?? null;
        
        // Find order by checkout ID or order number
        $order = null;
        if ($checkoutId) {
            $order = \App\Models\Order::where('payment_gateway_id', $checkoutId)->first();
        }
        
        if (!$order && $requestReferenceNumber) {
            $order = \App\Models\Order::where('order_number', $requestReferenceNumber)->first();
        }
        
        if ($order) {
            $order->markAsCancelled();
            
            Log::info('Order updated to cancelled', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        } else {
            Log::warning('Order not found for payment cancellation', [
                'checkout_id' => $checkoutId,
                'request_reference_number' => $requestReferenceNumber,
            ]);
        }
    }

    /**
     * Handle pending payment
     */
    private function handlePaymentPending($data)
    {
        Log::info('PayMaya Payment Pending', ['data' => $data]);
        
        $checkoutId = $data['checkoutId'] ?? null;
        
        // TODO: Update order status to pending
        // Usually no action needed, but you can log it
    }

    /**
     * Generate download tokens for all order items
     */
    private function generateDownloadTokens(\App\Models\Order $order): void
    {
        // Load order items with products
        $order->load('items.product');

        foreach ($order->items as $orderItem) {
            // Skip if product doesn't have a file
            if (!$orderItem->product || !$orderItem->product->file_path) {
                Log::info('Skipping download token generation - product has no file', [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                ]);
                continue;
            }

            // Check if download token already exists for this order item
            $existingDownload = ProductDownload::where('order_item_id', $orderItem->id)->first();
            if ($existingDownload) {
                Log::info('Download token already exists for order item', [
                    'order_item_id' => $orderItem->id,
                    'download_token' => $existingDownload->download_token,
                ]);
                continue;
            }

            // Generate secure download token
            $token = ProductDownload::generateToken();

            // Set expires_at to NULL for unlimited/no expiration downloads
            // This matches the user requirement for no download expiration

            // Create download record
            ProductDownload::create([
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'customer_id' => $order->customer_id,
                'guest_email' => $order->customer_id ? null : $order->guest_email,
                'download_token' => $token,
                'download_count' => 0,
                'max_downloads' => 999999, // Effectively unlimited downloads
                'expires_at' => null, // No expiration
            ]);

            Log::info('Download token generated for order item', [
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'download_token' => $token,
                'expires_at' => 'null (unlimited)',
            ]);
        }
    }

    /**
     * Send order confirmation email with Excel attachment
     * Only sends once per order to prevent duplicates
     */
    private function sendOrderConfirmationEmail(\App\Models\Order $order): void
    {
        try {
            // Reload order to get fresh data
            $order->refresh();
            
            // Check if order was just marked as paid (within last 10 seconds)
            // This prevents duplicate emails from multiple webhook calls
            if ($order->paid_at) {
                $paidAt = \Carbon\Carbon::parse($order->paid_at);
                $secondsSincePaid = now()->diffInSeconds($paidAt, false);
                
                // Only send email if order was paid very recently (within last 10 seconds)
                // This ensures we only send on the first webhook that marks it as paid
                if ($secondsSincePaid > 10) {
                    Log::info('Order confirmation email already sent (order paid more than 10 seconds ago)', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'paid_at' => $order->paid_at,
                        'seconds_since_paid' => $secondsSincePaid,
                    ]);
                    return;
                }
            } else {
                // Order not marked as paid yet, don't send email
                Log::warning('Cannot send order confirmation email - order not marked as paid', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
                return;
            }
            
            // Load customer relationship if exists
            $order->load('customer');
            
            // Get recipient email
            $email = $order->customer_id 
                ? $order->customer->email 
                : $order->guest_email;

            if (!$email) {
                Log::warning('Cannot send order confirmation email - no email address', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
                return;
            }

            // Use cache to prevent duplicate sends in the same request cycle
            $cacheKey = 'order_email_sent_' . $order->id;
            if (Cache::has($cacheKey)) {
                Log::info('Order confirmation email already sent in this request cycle', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
                return;
            }

            // Send email
            Mail::to($email)->send(new OrderConfirmationMail($order));
            
            // Mark as sent in cache (expires in 5 minutes to prevent duplicates)
            Cache::put($cacheKey, true, now()->addMinutes(5));

            // Persist that we've sent the confirmation email
            $order->update([
                'confirmation_email_sent_at' => now(),
            ]);
            
            Log::info('Order confirmation email sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'email' => $email,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending order confirmation email: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getTraceAsString(),
            ]);
            // Don't throw - email failure shouldn't break the payment flow
        }
    }
}

