<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['group', 'key', 'value', 'type', 'is_translatable'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_translatable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings'));
        static::deleted(fn () => Cache::forget('settings'));
    }

    /** All settings as a flat key => value map, cached. */
    public static function all_cached(): array
    {
        return Cache::rememberForever(
            'settings',
            fn () => static::query()->pluck('value', 'key')->toArray()
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_cached()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general'): self
    {
        return tap(
            static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]),
            fn () => Cache::forget('settings')
        );
    }
}
