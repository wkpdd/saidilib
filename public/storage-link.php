<?php
/**
 * One-shot storage link helper — for shared hosting (cPanel) without SSH access.
 *
 * Uploaded product photos are stored in  storage/app/public/  and served from the
 * URL  /storage/... , which requires the symlink  public/storage -> ../storage/app/public .
 * The `php artisan storage:link` command creates it; on hosts without a terminal,
 * open this file ONCE in your browser instead:
 *
 *     https://saidi.h47.io/storage-link.php
 *
 * Then DELETE this file for security.
 */

header('Content-Type: text/plain; charset=utf-8');

$target = __DIR__ . '/../storage/app/public';
$link   = __DIR__ . '/storage';

if (is_link($link) || is_dir($link)) {
    echo "✅ OK — 'public/storage' already exists. Uploads should display now.\n";
    echo "You can DELETE storage-link.php.\n";
    exit;
}

if (! is_dir($target)) {
    echo "❌ ERROR: storage target not found:\n   $target\n";
    echo "Make sure the whole app was uploaded (the storage/ folder).\n";
    exit;
}

if (@symlink($target, $link)) {
    echo "✅ SUCCESS — created  public/storage  ->  ../storage/app/public\n";
    echo "Reload a product page: the images should appear.\n";
    echo "Now DELETE storage-link.php for security.\n";
} else {
    $abs = realpath($target);
    echo "⚠️ Could not create the symlink automatically (host may block symlink()).\n";
    echo "Create it from cPanel File Manager, or ask support to run:\n";
    echo "   ln -s $abs $link\n";
}
