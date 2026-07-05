<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    protected $fillable = [
        'product_id', 'platform', 'status', 'external_id', 'permalink', 'message', 'created_by',
    ];

    public const PLATFORMS = [
        'facebook'  => 'Facebook',
        'instagram' => 'Instagram',
        'telegram'  => 'Telegram',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
