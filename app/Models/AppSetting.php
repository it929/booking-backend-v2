<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        $val = $setting->value;
        if (is_array($val) && array_key_exists('value', $val) && count($val) === 1) {
            return $val['value'];
        }
        return $val ?? $default;
    }

    public static function setSetting(string $key, mixed $value): static
    {
        $formattedValue = is_array($value) ? $value : ['value' => $value];
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $formattedValue]
        );
    }
}
