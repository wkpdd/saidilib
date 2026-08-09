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
        'refund_amount'    => 'decimal:2',
        'refunded_at'      => 'datetime',
        'provider_payload' => 'array',
        'dispatched_at'    => 'datetime',
        'has_stock_issue'  => 'boolean',
        'stock_issue_resolved_at' => 'datetime',
        'ready_at'         => 'datetime',
        'noest_activity'   => 'array',
        'noest_checked_at' => 'datetime',
    ];

    /** A shortage the admin still has to settle with the customer. */
    public function getHasOpenStockIssueAttribute(): bool
    {
        return $this->has_stock_issue && is_null($this->stock_issue_resolved_at);
    }

    public const STATUSES = [
        'pending', 'confirmed', 'preparing', 'ready', 'shipped',
        'delivered', 'cancelled', 'returned',
    ];

    public const REFUND_METHODS = [
        'cash'         => 'Espèces',
        'store_credit' => 'Avoir (crédit client)',
        'delivery'     => 'Via le livreur',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class)->latest();
    }

    /** Line prices can be edited only before the order is shipped/finalised. */
    public function getIsEditableAttribute(): bool
    {
        return in_array($this->status, ['pending', 'confirmed', 'preparing'], true);
    }

    public function getIsRefundedAttribute(): bool
    {
        return ! is_null($this->refunded_at);
    }

    /** Prepared by the team and waiting for the carrier. */
    public function getIsReadyAttribute(): bool
    {
        return ! is_null($this->ready_at) || $this->status === 'ready';
    }

    /** Created AND validated at Noest — visible to their logistics. */
    public function getIsCarrierValidatedAttribute(): bool
    {
        return (bool) ($this->provider_payload['validated'] ?? false);
    }

    /** Orders that can be pushed to / tracked with the Noest API. */
    public function scopeNoest($query)
    {
        return $query->where('delivery_provider', 'noest')->whereNotNull('tracking_number');
    }

    public const STATUS_LABELS = [
        'pending'   => 'En attente',
        'confirmed' => 'Confirmée',
        'preparing' => 'En préparation',
        'ready'     => 'Prête à expédier',
        'shipped'   => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
        'returned'  => 'Retournée',
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return [
            'pending'   => 'amber',
            'confirmed' => 'blue',
            'preparing' => 'indigo',
            'ready'     => 'teal',
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
