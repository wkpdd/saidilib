<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryIncident extends Model
{
    protected $fillable = [
        'product_id', 'product_name', 'type', 'quantity',
        'cost_estimate', 'reason', 'stock_adjusted', 'reported_by',
    ];

    protected $casts = [
        'cost_estimate'  => 'decimal:2',
        'stock_adjusted' => 'boolean',
    ];

    public const TYPES = [
        'lost'    => 'Perdu',
        'broken'  => 'Cassé / Endommagé',
        'expired' => 'Périmé',
        'theft'   => 'Vol',
        'other'   => 'Autre',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}
