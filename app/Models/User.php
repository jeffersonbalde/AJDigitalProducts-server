<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'otp_attempts',
        'otp_sent_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
     * Get the email verification OTPs for the user
     */
    public function emailVerificationOtps()
    {
        return $this->hasMany(EmailVerificationOtp::class);
    }

    /**
     * Get the latest valid OTP for the user
     */
    public function latestValidOtp()
    {
        return $this->emailVerificationOtps()
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }
}
