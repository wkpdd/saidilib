<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anonymous visitor counter for the storefront. Terminable: the write happens
 * AFTER the response is sent, so it costs the visitor nothing (low-bandwidth
 * priority). No cookies, no personal data — only a salted daily hash.
 */
class TrackVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
                return;
            }
            // Storefront pages only — not admin/API/assets.
            $path = $request->path();
            foreach (['admin', 'api', 'storage', 'build', 'img', 'up'] as $skip) {
                if ($path === $skip || str_starts_with($path, $skip . '/')) {
                    return;
                }
            }
            // Only count real page loads (not JSON polling like checkout fees).
            if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                return;
            }
            $ua = strtolower((string) $request->userAgent());
            if ($ua === '' || preg_match('/bot|crawl|spider|slurp|curl|wget|monitor|preview/', $ua)) {
                return;
            }

            // Same person = same hash for the day; changes daily → no tracking over time.
            $hash = sha1(now()->toDateString() . '|' . $request->ip() . '|' . $ua . '|' . config('app.key'));

            DB::statement(
                'INSERT INTO site_visits (day, visitor_hash, views) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE views = views + 1',
                [now()->toDateString(), $hash]
            );
        } catch (\Throwable $e) {
            // Counting must never break the site (e.g. table not migrated yet).
        }
    }
}
