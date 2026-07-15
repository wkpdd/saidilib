<?php

namespace App\Support;

/**
 * Resizes + re-compresses an image BEFORE it's saved to storage, so the
 * stored "original" itself is web-appropriate — not just its thumbnails.
 * This matters because a few places (social share, Open Graph tags, the
 * admin gallery, external links) reference the original file directly.
 *
 * A phone photo can easily be 4000px / 6-8MB; this caps it to a sane display
 * size and re-encodes as JPEG, typically cutting 70-90% of the file size
 * with no visible quality loss on screen.
 */
class ImageOptimizer
{
    /** Longest edge, in pixels, after optimisation. */
    private const MAX_DIMENSION = 2000;

    /** JPEG quality (0-100). 85 is visually clean while cutting weight hard. */
    private const QUALITY = 85;

    /**
     * Returns optimised JPEG bytes, or the original bytes unchanged if the
     * image can't be decoded or optimisation would not actually shrink it.
     */
    public static function optimize(string $bytes): string
    {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return $bytes;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_DIMENSION / max($w, $h));
        $targetW = max(1, (int) round($w * $scale));
        $targetH = max(1, (int) round($h * $scale));

        // Flatten onto white first — most sources are opaque photos, and this
        // keeps output format simple/consistent (always JPEG). Resampling
        // (even at the same size) also re-applies compression cleanly.
        $dst = imagecreatetruecolor($targetW, $targetH);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $w, $h);

        ob_start();
        imagejpeg($dst, null, self::QUALITY);
        $out = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        // Only use the optimised version if it's actually smaller.
        return ($out !== false && $out !== '' && strlen($out) < strlen($bytes)) ? $out : $bytes;
    }
}
