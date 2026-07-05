<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReceiptItem extends Model
{
    protected $fillable = [
        'stock_receipt_id', 'product_id', 'product_variant_id', 'product_name',
        'lot_number', 'expiry_date', 'quantity', 'unit_cost', 'line_total',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'unit_cost'   => 'decimal:2',
        'line_total'  => 'decimal:2',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
