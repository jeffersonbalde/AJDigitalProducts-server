<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_sub',
        'email_verified_at',
        'password',
        'is_active',
        'register_status',
        'otp_attempts',
        'otp_sent_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'otp_sent_at' => 'datetime',
        ];
    }

    /**
     * Get the email verification OTPs for the customer
     */
    public function emailVerificationOtps()
    {
        return $this->hasMany(EmailVerificationOtp::class, 'customer_id');
    }

    /**
     * Get the latest valid OTP for the customer
     */
    public function latestValidOtp()
    {
        return $this->emailVerificationOtps()
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Get the time logs for the customer
     */
    public function timeLogs()
    {
        return $this->hasMany(CustomerTimeLog::class);
    }

    /**
     * Get the orders for the customer
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the cart items for the customer
     */
    public function cartItems()
    {
        return $this->hasMany(CustomerCart::class);
    }
}

