<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One quantity break of a product: "from {min_qty} units, {discount_percent}% off". */
class ProductQuantityTier extends Model
{
    protected $guarded = [];

    protected $casts = [
        'min_qty'          => 'integer',
        'discount_percent' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
