<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $guarded = [];
    public $timestamps = true;

    public static function get(string $key, $default = null)
    {
        $all = Cache::rememberForever('settings.all', function () {
            return static::pluck('value', 'key')->toArray();
        });

        return $all[$key] ?? $default;
    }

    public static function put(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget('settings.all');
    }

    public static function flush(): void
    {
        Cache::forget('settings.all');
    }

    /** Allow image fields to hold either an uploaded path or a full URL. */
    public static function isExternal(?string $path): bool
    {
        return $path !== null && str_starts_with($path, 'http');
    }
}
