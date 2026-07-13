<?php

namespace App\Support;

/**
 * Stamps the store logo onto the bottom-right corner of an image — used when
 * importing product photos found via the Google Image Search tool, so
 * imported photos carry the store's branding once hosted on our own server.
 */
class Watermarker
{
    /** Logo width as a fraction of the source image's width. */
    private const SCALE = 0.16;

    /** Padding from the corner, as a fraction of the source image's width. */
    private const PADDING = 0.02;

    /** Blend strength (0-100) — kept under 100 so the photo stays visible beneath. */
    private const OPACITY = 80;

    /**
     * Composite $logoPath onto the bottom-right of $imageBytes and return the
     * result as JPEG bytes. Returns the original bytes unchanged if either
     * image fails to decode (never throws — a missing watermark shouldn't
     * block a photo import).
     */
    public static function apply(string $imageBytes, string $logoPath): string
    {
        $src = @imagecreatefromstring($imageBytes);
        if ($src === false) {
            return $imageBytes;
        }

        $logo = self::loadLogo($logoPath);
        if ($logo === null) {
            $out = self::toJpeg($src);
            imagedestroy($src);

            return $out;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $logoW = imagesx($logo);
        $logoH = imagesy($logo);

        $targetW = max(24, (int) round($srcW * self::SCALE));
        $targetH = (int) round($logoH * ($targetW / $logoW));
        $pad = (int) round($srcW * self::PADDING);

        $resizedLogo = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($resizedLogo, false);
        imagesavealpha($resizedLogo, true);
        imagecopyresampled($resizedLogo, $logo, 0, 0, 0, 0, $targetW, $targetH, $logoW, $logoH);

        $x = max(0, $srcW - $targetW - $pad);
        $y = max(0, $srcH - $targetH - $pad);

        imagealphablending($src, true);
        imagecopymerge($src, $resizedLogo, $x, $y, 0, 0, $targetW, $targetH, self::OPACITY);

        $out = self::toJpeg($src);
        imagedestroy($resizedLogo);
        imagedestroy($logo);
        imagedestroy($src);

        return $out;
    }

    private static function loadLogo(string $path): mixed
    {
        if (! is_file($path)) {
            return null;
        }
        $bytes = @file_get_contents($path);

        return $bytes === false ? null : (@imagecreatefromstring($bytes) ?: null);
    }

    private static function toJpeg($image): string
    {
        ob_start();
        imagejpeg($image, null, 88);

        return ob_get_clean();
    }
}
