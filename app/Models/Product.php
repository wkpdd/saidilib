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
        'price'               => 'decimal:2',
        'compare_at_price'    => 'decimal:2',
        'is_active'           => 'boolean',
        'is_featured'         => 'boolean',
        'is_new'              => 'boolean',
        'free_shipping'       => 'boolean',
        'free_shipping_until' => 'date',
        'track_stock'         => 'boolean',
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

    /** Quantity breaks, cheapest threshold first. */
    public function quantityTiers(): HasMany
    {
        return $this->hasMany(ProductQuantityTier::class)->orderBy('min_qty');
    }

    public function getNameAttribute(): string
    {
        return $this->tr('name') ?? '';
    }

    /**
     * Storefront display title: name + référence + marque combined,
     * e.g. "Crayon H2 P34 Techno". Empty parts are skipped, so products
     * without a SKU or brand just show their plain name.
     */
    public function getDisplayNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->name, $this->sku, $this->brand])));
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

    /** Compressed WebP hero image for the product detail page (falls back gracefully). */
    public function getHeroImageUrlAttribute(): string
    {
        $path = $this->mainImagePath();

        return ($path ? \App\Support\Thumbnailer::heroUrl($path) : null)
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

    /**
     * Quantity-break discount (%) that applies to a line of $qty units.
     *
     * Retail only: wholesale clients already buy at a negotiated price, so the
     * two never stack. Reads the loaded relation — eager-load `quantityTiers`
     * when calling this in a loop.
     */
    public function quantityDiscountPercent(int $qty, ?string $clientType = null): float
    {
        if (in_array($clientType, ['wholesale', 'super_wholesale'], true)) {
            return 0.0;
        }

        $best = 0.0;
        foreach ($this->quantityTiers as $tier) {
            if ($qty >= $tier->min_qty) {
                $best = max($best, (float) $tier->discount_percent);
            }
        }

        return $best;
    }

    /** Unit price actually charged for a line of $qty units. */
    public function unitPriceFor(int $qty, ?string $clientType = null): float
    {
        $base = $this->priceForTier($clientType);
        $percent = $this->quantityDiscountPercent($qty, $clientType);

        return $percent > 0 ? round($base * (1 - $percent / 100), 2) : $base;
    }

    /**
     * The next quantity break above $qty, for the "add N more for -X%" nudge.
     * Null when the buyer is already on the best tier (or gets none at all).
     */
    public function nextQuantityTier(int $qty, ?string $clientType = null): ?array
    {
        if (in_array($clientType, ['wholesale', 'super_wholesale'], true)) {
            return null;
        }

        $current = $this->quantityDiscountPercent($qty, $clientType);

        foreach ($this->quantityTiers as $tier) {
            if ($tier->min_qty > $qty && (float) $tier->discount_percent > $current) {
                return [
                    'min_qty'  => (int) $tier->min_qty,
                    'percent'  => (float) $tier->discount_percent,
                    'missing'  => (int) $tier->min_qty - $qty,
                ];
            }
        }

        return null;
    }

    /** True when this product advertises at least one quantity break. */
    public function getHasQuantityTiersAttribute(): bool
    {
        return $this->quantityTiers->isNotEmpty();
    }

    public function getDiscountPercentAttribute(): int
    {
        if (! $this->on_sale) {
            return 0;
        }

        return (int) round((1 - ($this->price / $this->compare_at_price)) * 100);
    }

    /**
     * Free-delivery campaign running right now for this product.
     * No end date = runs until the admin switches it off; with one, the offer
     * stays valid for the whole of that day.
     */
    public function getHasFreeShippingAttribute(): bool
    {
        return (bool) $this->free_shipping
            && (! $this->free_shipping_until || ! $this->free_shipping_until->endOfDay()->isPast());
    }

    /**
     * Units we can actually ship for a line, or null when stock isn't tracked
     * (untracked products never come up short). A variant is capped by its own
     * stock, since that's what the storefront advertises for it.
     */
    public function availableFor(?ProductVariant $variant = null): ?int
    {
        if (! $this->track_stock) {
            return null;
        }

        return ($variant && $variant->stock !== null)
            ? min((int) $this->stock, (int) $variant->stock)
            : (int) $this->stock;
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
