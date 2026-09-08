<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Get the order that owns the order item
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product for this order item
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the product download for this order item
     */
    public function download()
    {
        return $this->hasOne(ProductDownload::class);
    }

    /**
     * Calculate subtotal based on price and quantity
     */
    public function calculateSubtotal(): float
    {
        return $this->product_price * $this->quantity;
    }
}
