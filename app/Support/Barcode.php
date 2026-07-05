<?php

namespace App\Support;

/**
 * Tiny, dependency-free Code 128-B barcode generator that renders to inline SVG.
 * Kept in the codebase (no composer package) to honour the low-bandwidth,
 * offline-friendly ethos — a delivery slip must print even with no internet.
 */
class Barcode
{
    /** Canonical Code 128 pattern table (values 0..106; 106 = STOP). */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
        '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
        '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
        '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
        '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
        '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
        '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
        '211214', '211232', '2331112',
    ];

    private const START_B = 104;
    private const STOP = 106;

    /**
     * Return an <svg> string encoding $text as Code 128-B.
     *
     * @param int $module Width in px of the narrowest bar.
     * @param int $height Bar height in px.
     */
    public static function svg(string $text, int $module = 2, int $height = 60): string
    {
        // Keep only printable ASCII (Code 128-B covers 32..126).
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        if ($text === '') {
            $text = '0';
        }

        $codes = [self::START_B];
        $sum = self::START_B;
        $pos = 1;
        foreach (str_split($text) as $char) {
            $value = ord($char) - 32;
            $codes[] = $value;
            $sum += $value * $pos++;
        }
        $codes[] = $sum % 103;   // checksum
        $codes[] = self::STOP;

        // Build the bar pattern.
        $x = 0;
        $rects = '';
        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code];
            $isBar = true;
            foreach (str_split($pattern) as $w) {
                $width = (int) $w * $module;
                if ($isBar) {
                    $rects .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="' . $height . '"/>';
                }
                $x += $width;
                $isBar = ! $isBar;
            }
        }

        $totalWidth = $x;

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $totalWidth . '" height="' . $height . '" '
            . 'viewBox="0 0 ' . $totalWidth . ' ' . $height . '" fill="#000" shape-rendering="crispEdges">'
            . $rects . '</svg>';
    }
}
