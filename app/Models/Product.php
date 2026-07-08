<?php

namespace App\Models;

use App\Support\Localizable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, Localizable;

    protected $guarded = [];

    protected $casts = [
        'price'            => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'is_active'        => 'boolean',
        'is_featured'      => 'boolean',
        'is_new'           => 'boolean',
        'free_shipping'    => 'boolean',
        'track_stock'      => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function pixels(): BelongsToMany
    {
        return $this->belongsToMany(Pixel::class);
    }

    public function getNameAttribute(): string
    {
        return $this->tr('name') ?? '';
    }

    public function getShortDescAttribute(): ?string
    {
        return $this->tr('short_desc');
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->tr('description');
    }

    public function getMainImageUrlAttribute(): string
    {
        if ($this->main_image) {
            return Setting::isExternal($this->main_image)
                ? $this->main_image
                : asset('storage/' . $this->main_image);
        }

        $first = $this->images->first();
        if ($first) {
            return $first->url;
        }

        return 'https://placehold.co/800x800/eef2ff/2563eb?text=' . urlencode($this->name_fr);
    }

    /** Raw stored path (or external URL) of the primary image, if any. */
    public function mainImagePath(): ?string
    {
        return $this->main_image ?: $this->images->first()?->path;
    }

    /** Small WebP thumbnail URL for grid/card display (falls back gracefully). */
    public function getCardImageUrlAttribute(): string
    {
        $path = $this->mainImagePath();

        return ($path ? \App\Support\Thumbnailer::url($path, 300) : null)
            ?? $this->main_image_url;
    }

    /** Responsive srcset ("<url> 300w, <url> 600w") for the card image. */
    public function getCardSrcsetAttribute(): ?string
    {
        $path = $this->mainImagePath();
        if (! $path || Setting::isExternal($path)) {
            return null;
        }

        $set = [];
        foreach (\App\Support\Thumbnailer::WIDTHS as $w) {
            if ($url = \App\Support\Thumbnailer::url($path, $w)) {
                $set[] = "{$url} {$w}w";
            }
        }

        return $set ? implode(', ', $set) : null;
    }

    public function getOnSaleAttribute(): bool
    {
        return $this->compare_at_price && $this->compare_at_price > $this->price;
    }

    /**
     * Effective base unit price for a given client's pricing tier, with a
     * graceful fallback chain (super → wholesale → retail).
     */
    public function priceForTier(?string $tier): float
    {
        if ($tier === 'super_wholesale') {
            return (float) ($this->super_wholesale_price ?: $this->wholesale_price ?: $this->price);
        }
        if ($tier === 'wholesale') {
            return (float) ($this->wholesale_price ?: $this->price);
        }

        return (float) $this->price;
    }

    /** Price shown to whoever is browsing now (the logged-in client's tier). */
    public function getCurrentPriceAttribute(): float
    {
        $client = auth('client')->user();

        return $this->priceForTier($client?->type);
    }

    /** True when the current viewer is getting a tier discount below retail. */
    public function getHasTierPriceAttribute(): bool
    {
        return $this->current_price < (float) $this->price;
    }

    public function getDiscountPercentAttribute(): int
    {
        if (! $this->on_sale) {
            return 0;
        }

        return (int) round((1 - ($this->price / $this->compare_at_price)) * 100);
    }

    public function getInStockAttribute(): bool
    {
        if (! $this->track_stock) {
            return true;
        }

        return $this->stock > 0 || $this->variants->where('stock', '>', 0)->isNotEmpty();
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
