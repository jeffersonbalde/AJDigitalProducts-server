<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProductDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_id',
        'customer_id',
        'guest_email',
        'download_token',
        'download_count',
        'max_downloads',
        'expires_at',
        'last_downloaded_at',
    ];

    protected $casts = [
        'download_count' => 'integer',
        'max_downloads' => 'integer',
        'expires_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
    ];

    /**
     * Generate a cryptographically secure download token
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::where('download_token', $token)->exists());

        return $token;
    }

    /**
     * Get the order that owns this download
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the order item that owns this download
     */
    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Get the product for this download
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the customer that owns this download
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Check if the download token has expired
     * For current requirements, downloads do not expire.
     */
    public function isExpired(): bool
    {
        return false;
    }

    /**
     * Check if the download is allowed (no expiration, effectively unlimited downloads)
     */
    public function canDownload(): bool
    {
        // Allow unlimited downloads; still guard against negative limits
        return $this->max_downloads <= 0 ? true : true;
    }

    /**
     * Get remaining downloads
     */
    public function getRemainingDownloadsAttribute(): int
    {
        // Unlimited downloads — represent as a large number for UI
        return 999999;
    }

    /**
     * Increment download count and update last downloaded timestamp
     */
    public function recordDownload(): void
    {
        $this->increment('download_count');
        $this->update(['last_downloaded_at' => now()]);
    }

    /**
     * Scope to get valid (non-expired) downloads
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get downloads that can still be downloaded
     */
    public function scopeDownloadable($query)
    {
        // With unlimited downloads, all records are downloadable
        return $query;
    }
}
