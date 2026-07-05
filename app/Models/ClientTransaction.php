<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTransaction extends Model
{
    protected $fillable = [
        'client_id', 'type', 'amount', 'description', 'order_id', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const TYPES = [
        'debt'       => 'Dette (crédit accordé)',
        'payment'    => 'Paiement reçu',
        'adjustment' => 'Ajustement',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Signed effect on the client balance (+ owes more, - owes less). */
    public function getSignedAmountAttribute(): float
    {
        return $this->type === 'payment' ? -(float) $this->amount : (float) $this->amount;
    }
}
