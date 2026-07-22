<?php

namespace App\Models;

use App\Support\Localizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pack extends Model
{
    use Localizable;

    protected $guarded = [];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PackItem::class)->orderBy('sort_order');
    }

    public function getNameAttribute(): string
    {
        return $this->tr('name') ?? '';
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->tr('description');
    }

    /** Sum of the items at their current catalogue prices (variant delta included). */
    public function getItemsTotalAttribute(): float
    {
        return (float) $this->items->sum(function ($item) {
            $base = (float) ($item->product->price ?? 0) + (float) ($item->variant->price_delta ?? 0);

            return $base * $item->quantity;
        });
    }

    /** Price actually charged: promo override when set (and lower), else the sum. */
    public function getEffectivePriceAttribute(): float
    {
        $sum = $this->items_total;

        return $this->price !== null && (float) $this->price > 0 && (float) $this->price < $sum
            ? (float) $this->price
            : $sum;
    }

    public function getHasPromoAttribute(): bool
    {
        return $this->effective_price < $this->items_total;
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return \App\Support\Thumbnailer::url($this->image, 600) ?? asset('storage/' . $this->image);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public static function homeEnabled(): bool
    {
        return Setting::get('packs_enabled', '0') === '1';
    }
}
