<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SetLocale::class,
            \App\Http\Middleware\TrackVisit::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'fulladmin' => \App\Http\Middleware\EnsureFullAdmin::class,
            'perm' => \App\Http\Middleware\EnsurePermission::class,
            'api.token' => \App\Http\Middleware\AuthApiToken::class,
        ]);

        // Guests hitting admin routes go to the admin login, not a missing `login` route.
        // Storefront customer routes go to the customer login; everything else
        // (the admin) goes to the admin login.
        $middleware->redirectGuestsTo(fn ($request) => $request->is('compte*')
            ? route('account.login')
            : route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// cPanel layout where the main domain is locked to ~/public_html: the app
// lives in ~/saidiapp and the web root elsewhere. config_saidi.php sets
// SAIDI_PUBLIC_PATH so Vite/asset/public_path() point at the real web root.
// Absent in dev → no behaviour change.
if (($saidiPublic = env('SAIDI_PUBLIC_PATH')) && is_dir($saidiPublic)) {
    $app->usePublicPath($saidiPublic);
}

return $app;
