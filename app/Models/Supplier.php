<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'rc', 'nif', 'address', 'notes', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class)->latest();
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** Total value of goods received from this supplier. */
    public function getTotalPurchasedAttribute(): float
    {
        return (float) $this->receipts()->where('status', 'received')->sum('total_cost');
    }
}
