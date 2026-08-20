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

    /**
     * IDs of this category AND every descendant, at any depth (Scolaire → its
     * sub-categories → their sub-categories …). Used so product counts and the
     * category filter cover the whole subtree, not just direct children.
     */
    public function descendantIds(): array
    {
        return static::subtreeIds($this->id);
    }

    /** Subtree IDs for a root id. The (id,parent_id) map is loaded once per request. */
    public static function subtreeIds(int $rootId): array
    {
        static $byParent = null;
        if ($byParent === null) {
            $byParent = static::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        }

        $ids = [$rootId];
        $stack = [$rootId];
        while ($stack) {
            $pid = array_pop($stack);
            foreach ($byParent->get($pid, collect()) as $child) {
                $ids[] = (int) $child->id;
                $stack[] = (int) $child->id;
            }
        }

        return array_values(array_unique($ids));
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
