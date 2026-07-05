<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Client extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'type',
        'wilaya_id', 'commune', 'address', 'credit_limit', 'notes', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    public const TYPES = [
        'retail'    => 'Particulier',
        'wholesale' => 'Grossiste / Revendeur',
    ];

    protected function casts(): array
    {
        return [
            'password'     => 'hashed',
            'is_active'    => 'boolean',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ClientTransaction::class)->latest();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    /**
     * Outstanding balance the client owes: debts minus payments (adjustments
     * are signed by convention — positive raises debt, use payment for credit).
     */
    public function getBalanceAttribute(): float
    {
        $debits  = (float) $this->transactions()->whereIn('type', ['debt', 'adjustment'])->sum('amount');
        $credits = (float) $this->transactions()->where('type', 'payment')->sum('amount');

        return round($debits - $credits, 2);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->balance > (float) $this->credit_limit && $this->balance > 0;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
