<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomerPasswordResetToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'token',
        'expires_at',
        'is_used',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    /**
     * Generate a secure password reset token
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Get the customer that owns this reset token
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Check if the token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the token is valid (not used and not expired)
     */
    public function isValid(): bool
    {
        return !$this->is_used && !$this->isExpired();
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(): void
    {
        $this->update(['is_used' => true]);
    }
}
