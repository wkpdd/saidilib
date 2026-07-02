<?php

namespace App\Models;

use App\Support\Localizable;
use Illuminate\Database\Eloquent\Model;

class Wilaya extends Model
{
    use Localizable;

    protected $guarded = [];
    public $timestamps = true;

    protected $casts = [
        'home_fee'     => 'decimal:2',
        'stopdesk_fee' => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    public function getNameAttribute(): string
    {
        return $this->tr('name') ?? '';
    }

    public function getLabelAttribute(): string
    {
        return str_pad((string) $this->code, 2, '0', STR_PAD_LEFT) . ' - ' . $this->name;
    }

    public function feeFor(string $type): float
    {
        return $type === 'stopdesk' ? (float) $this->stopdesk_fee : (float) $this->home_fee;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
