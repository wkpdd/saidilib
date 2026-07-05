<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $fillable = ['type', 'icon', 'title', 'body', 'url', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }

    /** Convenience factory used across the app to raise an admin notification. */
    public static function raise(string $type, string $title, ?string $body = null, ?string $url = null, string $icon = '🔔'): self
    {
        return static::create(compact('type', 'title', 'body', 'url', 'icon'));
    }
}
