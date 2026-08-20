<?php

/*
 * dompdf: tell it the REAL web root explicitly.
 *
 * In the saididz.com cPanel layout the public directory is ~/public_html,
 * not saidiapp/public. dompdf resolves its base path from
 * config('dompdf.public_path') and, when null, falls back to
 * realpath(base_path('public')) — which does not exist here, so it throws
 * "Cannot resolve public path" and every PDF (price list, prep sheets) 500s.
 *
 * SAIDI_PUBLIC_PATH is set in config_saidi.php on production; public_path()
 * (already pointed at the real root by bootstrap/app.php) is the dev fallback.
 * Only this one key is overridden; all other dompdf defaults are merged from
 * the package.
 */

return [
    'public_path' => env('SAIDI_PUBLIC_PATH') ?: public_path(),
];
