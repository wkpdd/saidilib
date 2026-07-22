<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Rotates a stored product photo. Writes to a NEW filename (so browser/CDN
 * caches can never show the stale orientation), regenerates thumbnails,
 * updates the image row + product main_image, and removes the old files.
 */
class ImageEditor
{
    /** @param int $degrees 90 (right) or -90 (left) */
    public static function rotate(Product $product, ProductImage $image, int $degrees): bool
    {
        $disk = Storage::disk('public');
        if (\App\Models\Setting::isExternal($image->path) || ! $disk->exists($image->path)) {
            return false;
        }

        $src = @imagecreatefromstring($disk->get($image->path));
        if ($src === false) {
            return false;
        }

        // GD rotates counter-clockwise for positive angles.
        $rotated = imagerotate($src, $degrees === 90 ? -90 : 90, 0);
        imagedestroy($src);
        if ($rotated === false) {
            return false;
        }

        ob_start();
        imagejpeg($rotated, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($rotated);
        if ($bytes === false || $bytes === '') {
            return false;
        }

        $newPath = 'products/' . Str::random(32) . '.jpg';
        $disk->put($newPath, $bytes);
        Thumbnailer::generateAll($newPath);

        $oldPath = $image->path;
        $image->update(['path' => $newPath]);
        if ($product->main_image === $oldPath) {
            $product->update(['main_image' => $newPath]);
        }

        // Remove the old original + its thumbnails.
        $disk->delete($oldPath);
        $base = preg_replace('/\.[^.\/]+$/', '', $oldPath);
        foreach ([...Thumbnailer::WIDTHS, Thumbnailer::HERO_WIDTH] as $w) {
            $disk->delete("thumbs/{$w}/{$base}.webp");
        }

        return true;
    }
}
