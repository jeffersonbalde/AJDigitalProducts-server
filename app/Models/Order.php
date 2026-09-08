<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'guest_email',
        'guest_name',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'billing_address',
        'shipping_address',
        'payment_gateway_id',
        'payment_gateway_transaction_id',
        'paid_at',
        'completed_at',
        'cancelled_at',
        'confirmation_email_sent_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'confirmation_email_sent_at' => 'datetime',
    ];

    /**
     * Generate a unique order number
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');

        // Get the last order number for today
        $lastOrder = self::where('order_number', 'like', "{$prefix}-{$date}-%")
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastOrder) {
            // Extract the sequence number and increment
            $parts = explode('-', $lastOrder->order_number);
            $sequence = isset($parts[2]) ? (int) $parts[2] + 1 : 1;
        } else {
            $sequence = 1;
        }

        // Format: ORD-20260113-0001
        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    /**
     * Boot method to auto-generate order number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    /**
     * Get the customer that owns the order
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the items for the order
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the product downloads for this order
     */
    public function downloads()
    {
        return $this->hasMany(ProductDownload::class);
    }

    /**
     * Get the product for each order item (through items)
     */
    public function products()
    {
        return $this->hasManyThrough(Product::class, OrderItem::class, 'order_id', 'id', 'id', 'product_id');
    }

    /**
     * Check if order belongs to a customer (authenticated user)
     */
    public function belongsToCustomer($customerId): bool
    {
        return $this->customer_id === $customerId;
    }

    /**
     * Check if order belongs to a guest (by email)
     */
    public function belongsToGuest($email): bool
    {
        return $this->customer_id === null &&
               $this->guest_email !== null &&
               strtolower($this->guest_email) === strtolower($email);
    }

    /**
     * Mark order as paid
     */
    public function markAsPaid($transactionId = null): void
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_gateway_transaction_id' => $transactionId,
            'paid_at' => now(),
            'status' => 'processing',
        ]);
    }

    /**
     * Mark order as failed
     */
    public function markAsFailed(): void
    {
        $this->update([
            'payment_status' => 'failed',
        ]);
    }

    /**
     * Mark order as cancelled
     */
    public function markAsCancelled(): void
    {
        $nextPaymentStatus = $this->payment_status;

        if ($this->payment_status === 'paid') {
            $nextPaymentStatus = 'refunded';
        } elseif ($this->payment_status === 'pending') {
            $nextPaymentStatus = 'failed';
        }

        $this->update([
            'status' => 'cancelled',
            'payment_status' => $nextPaymentStatus,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Mark order as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
