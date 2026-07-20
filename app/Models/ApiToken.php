<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $guarded = [];

    protected $casts = ['last_used_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a new token for a user. Returns the PLAIN token (shown once to
     * the app); only its SHA-256 hash is stored.
     */
    public static function issue(User $user, ?string $deviceName = null): string
    {
        $plain = Str::random(48);

        static::create([
            'user_id'      => $user->id,
            'token_hash'   => hash('sha256', $plain),
            'device_name'  => $deviceName,
            'last_used_at' => now(),
        ]);

        return $plain;
    }

    public static function findValid(string $plain): ?self
    {
        return static::with('user')->where('token_hash', hash('sha256', $plain))->first();
    }
}
