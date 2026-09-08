<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCart extends Model
{
    protected $fillable = [
        'customer_id',
        'product_id',
        'quantity',
    ];

    /**
     * Get the customer that owns the cart item
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the product in the cart
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
