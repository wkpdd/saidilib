<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLocation extends Model
{
    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean'];

    public function levels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public static function defaultLocation(): ?self
    {
        return static::where('is_default', true)->first() ?? static::orderBy('sort_order')->first();
    }
}
