<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless(
            $user && $user->is_admin && $user->hasPermission($permission),
            403,
            "Vous n'avez pas la permission d'accéder à cette section."
        );

        return $next($request);
    }
}
