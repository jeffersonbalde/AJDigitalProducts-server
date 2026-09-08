<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Note: RefreshDatabase already runs migrations for the test database.

beforeEach(function () {
    // Create test products
    $this->product1 = Product::create([
        'title' => 'Test Product 1',
        'slug' => 'test-product-1',
        'price' => 100.00,
        'category' => 'Test',
        'is_active' => true,
    ]);

    $this->product2 = Product::create([
        'title' => 'Test Product 2',
        'slug' => 'test-product-2',
        'price' => 200.00,
        'category' => 'Test',
        'is_active' => true,
    ]);

    $this->inactiveProduct = Product::create([
        'title' => 'Inactive Product',
        'slug' => 'inactive-product',
        'price' => 150.00,
        'category' => 'Test',
        'is_active' => false,
    ]);

    // Create test customer
    $this->customer = Customer::create([
        'name' => 'Test Customer',
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'register_status' => 'verified',
        'email_verified_at' => now(),
    ]);
});

test('can create order as guest user', function () {
    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $this->product1->id,
                'quantity' => 2,
            ],
        ],
        'subtotal' => 200.00,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 200.00,
        'currency' => 'PHP',
        'guest_email' => 'guest@example.com',
        'guest_name' => 'Guest User',
        'billing_address' => [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'address' => '123 Test St',
            'city' => 'Manila',
            'country' => 'PH',
        ],
        'shipping_address' => [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'address' => '123 Test St',
            'city' => 'Manila',
            'country' => 'PH',
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'order' => [
                'id',
                'order_number',
                'guest_email',
                'guest_name',
                'status',
                'payment_status',
                'subtotal',
                'total_amount',
                'items',
            ],
        ]);

    $order = Order::first();
    expect($order)->not->toBeNull()
        ->and($order->guest_email)->toBe('guest@example.com')
        ->and($order->guest_name)->toBe('Guest User')
        ->and($order->customer_id)->toBeNull()
        ->and($order->status)->toBe('pending')
        ->and($order->payment_status)->toBe('pending')
        ->and($order->order_number)->toMatch('/^ORD-\d{8}-\d{4}$/')
        ->and($order->items)->toHaveCount(1);
});

test('can create order as authenticated customer', function () {
    $token = $this->customer->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $this->product1->id,
                    'quantity' => 1,
                ],
            ],
            'subtotal' => 100.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 100.00,
            'currency' => 'PHP',
            'billing_address' => [
                'name' => 'Test Customer',
                'email' => 'test@example.com',
                'address' => '123 Test St',
                'city' => 'Manila',
                'country' => 'PH',
            ],
            'shipping_address' => [
                'name' => 'Test Customer',
                'email' => 'test@example.com',
                'address' => '123 Test St',
                'city' => 'Manila',
                'country' => 'PH',
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $order = Order::first();
    expect($order)->not->toBeNull()
        ->and($order->customer_id)->toBe($this->customer->id)
        ->and($order->guest_email)->toBeNull()
        ->and($order->guest_name)->toBeNull();
});

test('order number is generated sequentially', function () {
    $order1 = Order::create([
        'customer_id' => null,
        'guest_email' => 'test1@example.com',
        'subtotal' => 100,
        'total_amount' => 100,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $order2 = Order::create([
        'customer_id' => null,
        'guest_email' => 'test2@example.com',
        'subtotal' => 200,
        'total_amount' => 200,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    expect($order1->order_number)->not->toBe($order2->order_number)
        ->and($order1->order_number)->toMatch('/^ORD-\d{8}-\d{4}$/')
        ->and($order2->order_number)->toMatch('/^ORD-\d{8}-\d{4}$/');
});

test('can create order with multiple items', function () {
    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $this->product1->id,
                'quantity' => 2,
            ],
            [
                'product_id' => $this->product2->id,
                'quantity' => 1,
            ],
        ],
        'subtotal' => 400.00,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 400.00,
        'currency' => 'PHP',
        'guest_email' => 'guest@example.com',
        'guest_name' => 'Guest User',
        'billing_address' => [],
        'shipping_address' => [],
    ]);

    $response->assertStatus(201);

    $order = Order::first();
    expect($order->items)->toHaveCount(2)
        ->and($order->items[0]->product_id)->toBe($this->product1->id)
        ->and($order->items[0]->quantity)->toBe(2)
        ->and($order->items[1]->product_id)->toBe($this->product2->id)
        ->and($order->items[1]->quantity)->toBe(1);
});

test('can create order with discount', function () {
    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $this->product1->id,
                'quantity' => 1,
            ],
        ],
        'subtotal' => 100.00,
        'tax_amount' => 0,
        'discount_amount' => 20.00,
        'total_amount' => 80.00,
        'currency' => 'PHP',
        'guest_email' => 'guest@example.com',
        'guest_name' => 'Guest User',
        'billing_address' => [],
        'shipping_address' => [],
    ]);

    $response->assertStatus(201);

    $order = Order::first();
    expect($order->discount_amount)->toEqual(20.00)
        ->and($order->total_amount)->toEqual(80.00);
});

test('rejects order with invalid product id', function () {
    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => 99999, // Non-existent
                'quantity' => 1,
            ],
        ],
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        'guest_email' => 'guest@example.com',
        'billing_address' => [],
        'shipping_address' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('rejects order with inactive product', function () {
    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $this->inactiveProduct->id,
                'quantity' => 1,
            ],
        ],
        'subtotal' => 150.00,
        'total_amount' => 150.00,
        'currency' => 'PHP',
        'guest_email' => 'guest@example.com',
        'billing_address' => [],
        'shipping_address' => [],
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'not available'));
});

test('recalculates totals if mismatch detected', function () {
    // Provide incorrect subtotal
    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $this->product1->id,
                'quantity' => 2,
            ],
        ],
        'subtotal' => 50.00, // Incorrect (should be 200.00)
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 50.00,
        'currency' => 'PHP',
        'guest_email' => 'guest@example.com',
        'billing_address' => [],
        'shipping_address' => [],
    ]);

    $response->assertStatus(201);

    $order = Order::first();
    // System should recalculate and use correct total
    expect($order->subtotal)->toEqual(200.00)
        ->and($order->total_amount)->toEqual(200.00);
});

test('requires guest email for guest orders', function () {
    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $this->product1->id,
                'quantity' => 1,
            ],
        ],
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        // No guest_email provided
        'billing_address' => [],
        'shipping_address' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('customer can retrieve their own order', function () {
    $token = $this->customer->createToken('test-token')->plainTextToken;

    $order = Order::create([
        'customer_id' => $this->customer->id,
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson("/api/orders/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('order.id', $order->id);
});

test('customer cannot retrieve other customer order', function () {
    $otherCustomer = Customer::create([
        'name' => 'Other Customer',
        'email' => 'other@example.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'register_status' => 'verified',
        'email_verified_at' => now(),
    ]);

    $token = $this->customer->createToken('test-token')->plainTextToken;

    $order = Order::create([
        'customer_id' => $otherCustomer->id,
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson("/api/orders/{$order->id}");

    $response->assertStatus(403)
        ->assertJsonPath('success', false);
});

test('guest can retrieve order by email', function () {
    $order = Order::create([
        'customer_id' => null,
        'guest_email' => 'guest@example.com',
        'guest_name' => 'Guest User',
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $response = $this->getJson("/api/orders/number/{$order->order_number}?guest_email=guest@example.com");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('order.id', $order->id);
});

test('guest cannot retrieve order with wrong email', function () {
    $order = Order::create([
        'customer_id' => null,
        'guest_email' => 'guest@example.com',
        'guest_name' => 'Guest User',
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $response = $this->getJson("/api/orders/number/{$order->order_number}?guest_email=wrong@example.com");

    $response->assertStatus(403)
        ->assertJsonPath('success', false);
});

test('customer can list their orders', function () {
    $token = $this->customer->createToken('test-token')->plainTextToken;

    // Create orders for this customer
    Order::create([
        'customer_id' => $this->customer->id,
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'paid',
    ]);

    Order::create([
        'customer_id' => $this->customer->id,
        'subtotal' => 200.00,
        'total_amount' => 200.00,
        'currency' => 'PHP',
        'status' => 'completed',
        'payment_status' => 'paid',
    ]);

    // Create order for another customer
    $otherCustomer = Customer::create([
        'name' => 'Other',
        'email' => 'other@example.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'register_status' => 'verified',
        'email_verified_at' => now(),
    ]);

    Order::create([
        'customer_id' => $otherCustomer->id,
        'subtotal' => 300.00,
        'total_amount' => 300.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->getJson('/api/orders');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'orders'); // Only this customer's orders
});

test('order mark as paid updates correctly', function () {
    $order = Order::create([
        'customer_id' => $this->customer->id,
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $order->markAsPaid('paymaya-txn-123');

    $order->refresh();
    expect($order->payment_status)->toBe('paid')
        ->and($order->status)->toBe('processing')
        ->and($order->payment_gateway_transaction_id)->toBe('paymaya-txn-123')
        ->and($order->paid_at)->not->toBeNull();
});

test('order mark as failed updates correctly', function () {
    $order = Order::create([
        'customer_id' => $this->customer->id,
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $order->markAsFailed();

    $order->refresh();
    expect($order->payment_status)->toBe('failed');
});

test('order mark as cancelled updates correctly', function () {
    $order = Order::create([
        'customer_id' => $this->customer->id,
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $order->markAsCancelled();

    $order->refresh();
    expect($order->status)->toBe('cancelled')
        ->and($order->payment_status)->toBe('failed')
        ->and($order->cancelled_at)->not->toBeNull();
});

test('order items preserve product information', function () {
    $order = Order::create([
        'customer_id' => $this->customer->id,
        'subtotal' => 100.00,
        'total_amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $this->product1->id,
        'product_name' => $this->product1->title,
        'product_price' => $this->product1->price,
        'quantity' => 1,
        'subtotal' => $this->product1->price,
    ]);

    // Delete the product
    $this->product1->delete();

    // Order item should still be accessible
    $order->refresh();
    expect($order->items)->toHaveCount(1)
        ->and($order->items[0]->product_name)->toBe('Test Product 1')
        ->and($order->items[0]->product_price)->toEqual(100.00);
});

test('order totals are calculated correctly', function () {
    $order = Order::create([
        'customer_id' => $this->customer->id,
        'subtotal' => 300.00,
        'tax_amount' => 36.00,
        'discount_amount' => 50.00,
        'total_amount' => 286.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    // Verify: total = subtotal + tax - discount
    $expectedTotal = $order->subtotal + $order->tax_amount - $order->discount_amount;
    expect($order->total_amount)->toEqual($expectedTotal);
});

test('order item subtotal is calculated correctly', function () {
    $order = Order::create([
        'customer_id' => $this->customer->id,
        'subtotal' => 200.00,
        'total_amount' => 200.00,
        'currency' => 'PHP',
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $this->product1->id,
        'product_name' => $this->product1->title,
        'product_price' => 100.00,
        'quantity' => 2,
        'subtotal' => 200.00,
    ]);

    expect($orderItem->subtotal)->toEqual(200.00)
        ->and($orderItem->calculateSubtotal())->toEqual(200.00);
});
