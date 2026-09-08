<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public $timestamps = true;

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $setting = self::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public static function setValue(string $key, ?string $value): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
