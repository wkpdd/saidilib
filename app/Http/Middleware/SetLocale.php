<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['ar', 'fr'];

    public const DEFAULT = 'ar';

    public function handle(Request $request, Closure $next): Response
    {
        // The back office is French-only (its layout is hardcoded LTR/fr),
        // so keep validation & pagination strings French there.
        if ($request->is('admin', 'admin/*')) {
            App::setLocale('fr');

            return $next($request);
        }

        $locale = Session::get('locale', config('app.locale', self::DEFAULT));

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = self::DEFAULT;
        }

        App::setLocale($locale);

        return $next($request);
    }
}
