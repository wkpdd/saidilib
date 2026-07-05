<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Generates cached, downscaled WebP thumbnails from images stored on the
 * "public" disk. Thumbnails live under `thumbs/{width}/<original-path>.webp`
 * so they are served as static files by the web server after first generation.
 *
 * External image URLs (admin-pasted links, placeholders) are left untouched —
 * we only optimise images we actually host.
 */
class Thumbnailer
{
    /** Widths (px) we generate for responsive srcsets. */
    public const WIDTHS = [300, 600];

    /** WebP quality (0-100). 78 is visually clean while cutting weight hard. */
    private const QUALITY = 78;

    /**
     * Return the public URL of a cached thumbnail at the given width,
     * generating it on demand. Falls back to the original asset URL when the
     * source is external, missing, or cannot be processed.
     */
    public static function url(?string $path, int $width): ?string
    {
        if (! $path) {
            return null;
        }

        if (Setting::isExternal($path)) {
            return $path;
        }

        $thumbPath = self::thumbPath($path, $width);
        $disk = Storage::disk('public');

        if (! $disk->exists($thumbPath)) {
            if (! self::generate($path, $width)) {
                return $disk->exists($path) ? asset('storage/' . $path) : null;
            }
        }

        return asset('storage/' . $thumbPath);
    }

    /**
     * Generate every configured width for a stored image (used on upload and
     * by the backfill command). Returns the number of thumbnails written.
     */
    public static function generateAll(string $path): int
    {
        if (Setting::isExternal($path)) {
            return 0;
        }

        $count = 0;
        foreach (self::WIDTHS as $w) {
            if (self::generate($path, $w)) {
                $count++;
            }
        }

        return $count;
    }

    private static function thumbPath(string $path, int $width): string
    {
        $withoutExt = preg_replace('/\.[^.\/]+$/', '', $path);

        return "thumbs/{$width}/{$withoutExt}.webp";
    }

    /**
     * Create one WebP thumbnail. Returns true on success (or if an up-to-date
     * thumbnail already exists), false when the source can't be read/processed.
     */
    private static function generate(string $path, int $width): bool
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path) || ! function_exists('imagewebp')) {
            return false;
        }

        $thumbPath = self::thumbPath($path, $width);
        if ($disk->exists($thumbPath) && $disk->lastModified($thumbPath) >= $disk->lastModified($path)) {
            return true;
        }

        $src = @imagecreatefromstring($disk->get($path));
        if ($src === false) {
            return false;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // Never upscale — small originals are served as-is.
        $targetW = min($width, $srcW);
        $targetH = (int) round($srcH * ($targetW / $srcW));

        $dst = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

        ob_start();
        $ok = imagewebp($dst, null, self::QUALITY);
        $data = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if (! $ok || $data === '') {
            return false;
        }

        return $disk->put($thumbPath, $data);
    }
}
