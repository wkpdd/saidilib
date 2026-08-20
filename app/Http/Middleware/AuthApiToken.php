<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the native staff app. Resolves the token's user so
 * downstream middleware (perm:*) and controllers see $request->user() exactly
 * like a session-authenticated admin.
 */
class AuthApiToken
{
    /** A token unused for this many days is treated as expired and deleted. */
    private const IDLE_EXPIRY_DAYS = 45;

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::findValid($plain) : null;

        if (! $token || ! $token->user || ! $token->user->is_active || ! $token->user->is_admin) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        // Idle expiry: an abandoned/leaked token stops working after 45 days of
        // no use (an actively-used one refreshes last_used_at every minute, so
        // it never expires). Bounds the window of a stolen token.
        if ($token->last_used_at && $token->last_used_at->lt(now()->subDays(self::IDLE_EXPIRY_DAYS))) {
            $token->delete();

            return response()->json(['message' => 'Session expirée — reconnectez-vous.'], 401);
        }

        // Cheap heartbeat — at most one write per minute per token.
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
