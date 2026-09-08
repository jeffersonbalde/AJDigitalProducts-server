<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'price',
        'old_price',
        'on_sale',
        'category',
        'slug',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'thumbnail_image',
        'feature_images',
        'is_active',
        'is_featured',
        'is_bestseller',
        'is_new_arrival',
        'featured_order',
        'bestseller_order',
        'new_arrival_order',
        'added_by_user_id',
        'added_by_user_type',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'old_price' => 'decimal:2',
        'on_sale' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_bestseller' => 'boolean',
        'is_new_arrival' => 'boolean',
        'feature_images' => 'array',
    ];

    /**
     * Generate slug from title
     */
    public static function createSlug($title)
    {
        return strtolower(
            preg_replace(
                '/[^A-Za-z0-9-]+/',
                '-',
                str_replace('&', 'and', $title)
            )
        );
    }

    /**
     * Get the admin who added this product
     */
    public function addedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'added_by_user_id');
    }

    /**
     * Get the personnel who added this product
     */
    public function addedByPersonnel()
    {
        return $this->belongsTo(Personnel::class, 'added_by_user_id');
    }

    /**
     * Get the user who added this product (accessor that returns the appropriate user)
     */
    public function getAddedByAttribute()
    {
        if (! $this->added_by_user_id || ! $this->added_by_user_type) {
            return null;
        }

        if ($this->added_by_user_type === 'admin') {
            return $this->addedByAdmin;
        } elseif ($this->added_by_user_type === 'personnel') {
            return $this->addedByPersonnel;
        }

        return null;
    }

    /**
     * Get the name of the user who added this product
     */
    public function getAddedByNameAttribute()
    {
        if (! $this->added_by_user_id || ! $this->added_by_user_type) {
            return null;
        }

        if ($this->added_by_user_type === 'admin' && $this->relationLoaded('addedByAdmin')) {
            return $this->addedByAdmin ? ($this->addedByAdmin->name ?? $this->addedByAdmin->username) : null;
        } elseif ($this->added_by_user_type === 'personnel' && $this->relationLoaded('addedByPersonnel')) {
            return $this->addedByPersonnel ? $this->addedByPersonnel->name : null;
        }

        // Fallback: load the user if not already loaded
        if ($this->added_by_user_type === 'admin') {
            $admin = Admin::find($this->added_by_user_id);

            return $admin ? ($admin->name ?? $admin->username) : null;
        } elseif ($this->added_by_user_type === 'personnel') {
            $personnel = Personnel::find($this->added_by_user_id);

            return $personnel ? $personnel->name : null;
        }

        return null;
    }

    /**
     * Get collections this product belongs to
     */
    public function collections()
    {
        return $this->belongsToMany(ProductCollection::class, 'collection_product', 'product_id', 'collection_id')
            ->withPivot('display_order', 'added_at')
            ->orderBy('collection_product.display_order')
            ->orderBy('collection_product.added_at', 'desc');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }
}
