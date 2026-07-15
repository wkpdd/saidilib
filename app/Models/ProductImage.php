<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return Setting::isExternal($this->path)
            ? $this->path
            : asset('storage/' . $this->path);
    }

    /** Compressed WebP hero-size URL (falls back to the raw asset URL for external images). */
    public function getHeroUrlAttribute(): string
    {
        return \App\Support\Thumbnailer::heroUrl($this->path) ?? $this->url;
    }

    /** Small WebP thumbnail URL for gallery strips (falls back to the raw asset URL). */
    public function getThumbUrlAttribute(): string
    {
        return \App\Support\Thumbnailer::url($this->path, 300) ?? $this->url;
    }
}
