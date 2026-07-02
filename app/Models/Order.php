<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'subtotal'         => 'decimal:2',
        'delivery_fee'     => 'decimal:2',
        'discount'         => 'decimal:2',
        'total'            => 'decimal:2',
        'provider_payload' => 'array',
        'dispatched_at'    => 'datetime',
    ];

    public const STATUSES = [
        'pending', 'confirmed', 'preparing', 'shipped',
        'delivered', 'cancelled', 'returned',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function getStatusColorAttribute(): string
    {
        return [
            'pending'   => 'amber',
            'confirmed' => 'blue',
            'preparing' => 'indigo',
            'shipped'   => 'cyan',
            'delivered' => 'green',
            'cancelled' => 'red',
            'returned'  => 'rose',
        ][$this->status] ?? 'gray';
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'SAIDI-' . strtoupper(bin2hex(random_bytes(3)));
        } while (self::where('reference', $ref)->exists());

        return $ref;
    }
}
