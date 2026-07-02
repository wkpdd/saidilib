<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFullAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user() && $request->user()->isFullAdmin(),
            403,
            "Seuls les administrateurs peuvent gérer l'équipe et les paramètres."
        );

        return $next($request);
    }
}
