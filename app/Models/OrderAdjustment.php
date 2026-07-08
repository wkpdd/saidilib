<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAdjustment extends Model
{
    protected $fillable = [
        'order_id', 'order_item_id', 'label',
        'old_price', 'new_price', 'old_total', 'new_total', 'reason', 'created_by',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'old_total' => 'decimal:2',
        'new_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
