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
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::findValid($plain) : null;

        if (! $token || ! $token->user || ! $token->user->is_active || ! $token->user->is_admin) {
            return response()->json(['message' => 'Non authentifié.'], 401);
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
