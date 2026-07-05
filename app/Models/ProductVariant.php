<?php

namespace App\Models;

use App\Support\Localizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use Localizable;

    protected $guarded = [];

    protected $casts = [
        'price_delta' => 'decimal:2',
        'is_default'  => 'boolean',
        'track_stock' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    public function getLabelAttribute(): string
    {
        $tr = $this->tr('label');
        if ($tr) {
            return $tr;
        }

        return trim(implode(' · ', array_filter([$this->color, $this->size]))) ?: '';
    }

    public function getPriceAttribute(): float
    {
        return (float) $this->product->price + (float) $this->price_delta;
    }

    /** A variant is available when it has stock. */
    public function getInStockAttribute(): bool
    {
        return (int) $this->stock > 0;
    }
}
