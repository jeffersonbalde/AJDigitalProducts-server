<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Create a new order
     * Supports both authenticated users and guests
     */
    public function store(Request $request)
    {
        // Get authenticated customer first to determine validation rules
        // Since route is public, we need to manually check for token
        $customer = null;
        $customerId = null;

        // Check if Authorization header is present
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = str_replace('Bearer ', '', $authHeader);

            // Find the token in personal_access_tokens table
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($tokenModel) {
                // Get the tokenable (customer) model
                $customer = $tokenModel->tokenable;
                if ($customer instanceof \App\Models\Customer) {
                    $customerId = $customer->id;
                }
            }
        }

        // Fallback to $request->user() if available (in case route has auth middleware)
        if (! $customer) {
            $customer = $request->user();
            $customerId = $customer ? $customer->id : null;
        }

        // Log incoming request for debugging
        Log::info('Order creation request received', [
            'items_count' => count($request->input('items', [])),
            'items' => $request->input('items'),
            'subtotal' => $request->input('subtotal'),
            'total_amount' => $request->input('total_amount'),
            'guest_email' => $request->input('guest_email'),
            'has_customer' => $customerId ? true : false,
            'customer_id' => $customerId,
        ]);

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'billing_address' => 'nullable|array',
            'shipping_address' => 'nullable|array',
            // Guest order fields - only required if not authenticated
            'guest_email' => $customerId ? 'nullable|email' : 'required|email',
            'guest_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::warning('Order creation validation failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Customer already retrieved above for validation
            // No need to check guest_email again - validation handles it

            // Calculate totals
            $subtotal = $request->subtotal;
            $taxAmount = $request->tax_amount ?? 0;
            $discountAmount = $request->discount_amount ?? 0;
            $totalAmount = $request->total_amount;
            $currency = $request->currency ?? 'PHP';

            // Validate items and calculate actual totals
            $items = [];
            $calculatedSubtotal = 0;

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if (! $product) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => "Product with ID {$item['product_id']} not found",
                    ], 404);
                }

                if (! $product->is_active) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => "Product {$product->title} is not available",
                    ], 400);
                }

                $quantity = (int) $item['quantity'];
                $price = (float) $product->price;
                $itemSubtotal = $price * $quantity;
                $calculatedSubtotal += $itemSubtotal;

                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->title,
                    'product_price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $itemSubtotal,
                ];
            }

            // Verify totals match (allow small floating point differences)
            if (abs($calculatedSubtotal - $subtotal) > 0.01) {
                Log::warning('Order total mismatch', [
                    'calculated' => $calculatedSubtotal,
                    'provided' => $subtotal,
                ]);
                // Use calculated subtotal for security
                $subtotal = $calculatedSubtotal;
                $totalAmount = $subtotal + $taxAmount - $discountAmount;
            }

            // Create order
            $order = Order::create([
                'customer_id' => $customerId,
                'guest_email' => $customerId ? null : $request->guest_email,
                'guest_name' => $customerId ? null : ($request->guest_name ?? 'Guest'),
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'billing_address' => $request->billing_address,
                'shipping_address' => $request->shipping_address,
            ]);

            // Create order items
            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_price' => $item['product_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            DB::commit();

            // Load relationships for response
            $order->load('items.product');

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'order' => $order,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order details
     * Customers can only see their own orders
     * Admins can see all orders
     */
    public function show(Request $request, $id)
    {
        try {
            $order = Order::with(['items.product', 'items.download', 'customer'])->find($id);

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // Check access control
            $user = null;

            // Try to get authenticated user from token (since this route is public)
            $authHeader = $request->header('Authorization');
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $token = str_replace('Bearer ', '', $authHeader);
                $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($tokenModel) {
                    $user = $tokenModel->tokenable;
                }
            }

            // Fallback to $request->user() if available (in case route has auth middleware)
            if (! $user) {
                $user = $request->user();
            }

            // Admin can see all orders
            if ($user && ($user instanceof \App\Models\Admin || $user instanceof \App\Models\Personnel)) {
                return response()->json([
                    'success' => true,
                    'order' => $order,
                ]);
            }

            // Customer can only see their own orders
            if ($user && $user instanceof Customer) {
                if ($order->customer_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access to this order',
                    ], 403);
                }
            } else {
                // Guest can only see orders by email
                $guestEmail = $request->input('guest_email');
                if (! $guestEmail || ! $order->belongsToGuest($guestEmail)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access to this order',
                    ], 403);
                }
            }

            return response()->json([
                'success' => true,
                'order' => $order,
            ]);

        } catch (\Exception $e) {
            Log::error('Order retrieval failed', [
                'order_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order by order number
     */
    public function showByOrderNumber(Request $request, $orderNumber)
    {
        try {
            $order = Order::with(['items.product', 'items.download', 'customer'])
                ->where('order_number', $orderNumber)
                ->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // Check access control
            // Since this is for order confirmation page, we allow access if:
            // 1. User is admin/personnel (can see all)
            // 2. User is the customer who owns the order
            // 3. Order is a guest order and email matches (for guest orders)
            // 4. Order was just created (within last 30 minutes) - allow access for confirmation

            $user = null;
            $customerId = null;

            // Try to get authenticated user from token (since route is public)
            $authHeader = $request->header('Authorization');
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $token = str_replace('Bearer ', '', $authHeader);
                $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($tokenModel) {
                    $user = $tokenModel->tokenable;
                    if ($user instanceof Customer) {
                        $customerId = $user->id;
                    }
                }
            }

            // Fallback to $request->user() if available
            if (! $user) {
                $user = $request->user();
                if ($user instanceof Customer) {
                    $customerId = $user->id;
                }
            }

            // Admin/Personnel can see all orders
            if ($user && ($user instanceof \App\Models\Admin || $user instanceof \App\Models\Personnel)) {
                return response()->json([
                    'success' => true,
                    'order' => $order,
                ]);
            }

            // Customer can see their own orders
            if ($customerId && $order->customer_id === $customerId) {
                return response()->json([
                    'success' => true,
                    'order' => $order,
                ]);
            }

            // For guest orders, check email
            if ($order->customer_id === null) {
                $guestEmail = $request->input('guest_email');
                if ($guestEmail && $order->guest_email === $guestEmail) {
                    return response()->json([
                        'success' => true,
                        'order' => $order,
                    ]);
                }
            }

            // For order confirmation page, allow access if:
            // 1. Order was created recently (within 1 hour) - helps with PayMaya redirect
            // 2. AND order has customer_id that matches authenticated customer
            $orderAge = now()->diffInMinutes($order->created_at);
            if ($orderAge <= 60 && $order->customer_id && $customerId && $order->customer_id === $customerId) {
                return response()->json([
                    'success' => true,
                    'order' => $order,
                ]);
            }

            // Also allow if customer_id matches (even if order is older)
            // This handles cases where customer views their order later
            if ($order->customer_id && $customerId && $order->customer_id === $customerId) {
                return response()->json([
                    'success' => true,
                    'order' => $order,
                ]);
            }

            // Default: deny access
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this order. Please login if this is your order.',
            ], 403);

        } catch (\Exception $e) {
            Log::error('Order retrieval by number failed', [
                'order_number' => $orderNumber,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List orders
     * Customers see only their paid orders by default
     * Admins see all orders
     */
    public function index(Request $request)
    {
        try {
            // Manually authenticate customer (since route uses auth:sanctum, but be explicit)
            $user = $request->user();
            $customerId = null;

            // Also check Authorization header manually for consistency
            $authHeader = $request->header('Authorization');
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $token = str_replace('Bearer ', '', $authHeader);
                $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($tokenModel) {
                    $tokenUser = $tokenModel->tokenable;
                    if ($tokenUser instanceof Customer) {
                        $customerId = $tokenUser->id;
                        $user = $tokenUser;
                    }
                }
            }

            // Fallback to $request->user() if available
            if (! $user && $request->user()) {
                $user = $request->user();
                if ($user instanceof Customer) {
                    $customerId = $user->id;
                }
            }

            $perPage = $request->input('per_page', 15);
            $page = $request->input('page', 1);

            $query = Order::with(['items.product', 'items.download', 'customer']);

            // Filter by customer if authenticated customer
            if ($customerId) {
                $query->where('customer_id', $customerId);
                // For customers, default to only showing paid orders unless explicitly requested otherwise
                if (! $request->has('payment_status')) {
                    $query->where('payment_status', 'paid');
                }
            } elseif (! $user) {
                // Guests cannot list orders without email
                $guestEmail = $request->input('guest_email');
                if (! $guestEmail) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Guest email is required to view orders',
                    ], 422);
                }
                $query->where('guest_email', $guestEmail)
                    ->whereNull('customer_id');
                // For guests, also default to paid orders
                if (! $request->has('payment_status')) {
                    $query->where('payment_status', 'paid');
                }
            }
            // Admins and personnel see all orders (no filter)

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by payment_status if provided (overrides default for customers)
            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Sort by created_at desc (newest first)
            $query->orderBy('created_at', 'desc');

            $orders = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'orders' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Order listing failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update order status (Admin only)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,completed,cancelled',
            'payment_status' => 'nullable|in:pending,paid,failed,refunded',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = Order::find($id);

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // Check if user is admin or personnel
            $user = $request->user();
            if (! $user || (! ($user instanceof \App\Models\Admin) && ! ($user instanceof \App\Models\Personnel))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.',
                ], 403);
            }

            $order->status = $request->status;

            if ($request->has('payment_status')) {
                $order->payment_status = $request->payment_status;
            }

            // Set timestamps based on status
            if ($request->status === 'completed' && ! $order->completed_at) {
                $order->completed_at = now();
            } elseif ($request->status === 'cancelled' && ! $order->cancelled_at) {
                $order->cancelled_at = now();
            }

            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'order' => $order->load(['items.product', 'customer']),
            ]);

        } catch (\Exception $e) {
            Log::error('Order status update failed', [
                'order_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
