<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerTimeLog extends Model
{
    use HasFactory;

    protected $table = 'customer_time_logs';

    protected $fillable = [
        'customer_id',
        'action',
        'ip_address',
        'user_agent',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the time log
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
