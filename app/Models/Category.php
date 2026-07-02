<?php

namespace App\Models;

use App\Support\Localizable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, Localizable;

    protected $guarded = [];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getNameAttribute(): string
    {
        return $this->tr('name') ?? '';
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->tr('description');
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image
            ? asset('storage/' . $this->image)
            : 'https://placehold.co/600x400/eef2ff/2563eb?text=' . urlencode($this->name_fr);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
