<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomerCart;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Get the authenticated customer's cart
     */
    public function index(Request $request)
    {
        $customer = $request->user(); // Get authenticated customer from Sanctum
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $cartItems = CustomerCart::where('customer_id', $customer->id)
            ->with('product')
            ->get();

        // Format cart items to match frontend structure
        $formattedItems = $cartItems->map(function ($item) {
            $product = $item->product;
            if (!$product) {
                return null; // Skip if product was deleted
            }

            return [
                'id' => (string)$product->id,
                'title' => $product->title,
                'name' => $product->title, // Alias for compatibility
                'price' => (float)$product->price,
                'image' => $product->thumbnail_image ? 
                    (str_starts_with($product->thumbnail_image, 'http') ? 
                        $product->thumbnail_image : 
                        asset('storage/' . $product->thumbnail_image)) : 
                    null,
                'quantity' => $item->quantity,
            ];
        })->filter(); // Remove null items

        return response()->json([
            'success' => true,
            'cart' => $formattedItems->values(),
        ]);
    }

    /**
     * Save/update the customer's cart
     * Accepts array of cart items: [{product_id, quantity}, ...]
     */
    public function save(Request $request)
    {
        $customer = $request->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $items = $request->input('items');
        $customerId = $customer->id;

        // Delete existing cart items
        CustomerCart::where('customer_id', $customerId)->delete();

        // Insert new cart items
        foreach ($items as $item) {
            CustomerCart::create([
                'customer_id' => $customerId,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        // Return updated cart
        return $this->index($request);
    }

    /**
     * Add a single item to cart
     */
    public function add(Request $request)
    {
        $customer = $request->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'integer|min:1',
        ]);

        $productId = $request->input('product_id');
        $quantity = $request->input('quantity', 1);
        $customerId = $customer->id;

        // Check if item already exists in cart
        $cartItem = CustomerCart::where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->first();

        if ($cartItem) {
            // Update quantity
            $cartItem->quantity += $quantity;
            $cartItem->save();
        } else {
            // Create new cart item
            CustomerCart::create([
                'customer_id' => $customerId,
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
        }

        return $this->index($request);
    }

    /**
     * Update quantity of a cart item
     */
    public function update(Request $request, $productId)
    {
        $customer = $request->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cartItem = CustomerCart::where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found',
            ], 404);
        }

        $cartItem->quantity = $request->input('quantity');
        $cartItem->save();

        return $this->index($request);
    }

    /**
     * Remove an item from cart
     */
    public function remove(Request $request, $productId)
    {
        $customer = $request->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        CustomerCart::where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->delete();

        return $this->index($request);
    }

    /**
     * Clear the customer's cart
     */
    public function clear(Request $request)
    {
        $customer = $request->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        CustomerCart::where('customer_id', $customer->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared',
            'cart' => [],
        ]);
    }

    /**
     * Merge guest cart (from localStorage) with customer cart
     * Accepts array of cart items: [{id, quantity}, ...]
     * Uses max quantity strategy to prevent duplication
     */
    public function merge(Request $request)
    {
        $customer = $request->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $guestItems = $request->input('items');
        $customerId = $customer->id;

        foreach ($guestItems as $item) {
            $productId = (int)$item['id'];
            $quantity = (int)$item['quantity'];

            // Check if product exists
            $product = Product::find($productId);
            if (!$product) {
                continue; // Skip invalid products
            }

            // Check if item already exists in customer cart
            $cartItem = CustomerCart::where('customer_id', $customerId)
                ->where('product_id', $productId)
                ->first();

            if ($cartItem) {
                // Item already exists in backend cart - keep backend quantity (don't merge)
                // This prevents duplication. Backend cart is the source of truth.
                // Only add items that don't exist in backend cart.
                continue;
            } else {
                // Create new cart item (item doesn't exist in backend)
                CustomerCart::create([
                    'customer_id' => $customerId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ]);
            }
        }

        // Return merged cart
        return $this->index($request);
    }
}
