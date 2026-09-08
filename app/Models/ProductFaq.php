<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductFaq extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFaqFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'question',
        'answer',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
