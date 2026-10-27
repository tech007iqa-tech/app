<?php
/**
 * Zero-Dependency Pure PHP Code 128 Barcode Generator
 * Generates standards-compliant, 100% vector SVG Code 128 (Subset B) barcodes
 * Scannable by any 1D/2D optical or laser warehouse barcode scanner.
 */

class Barcode128 {
    // Code 128 Pattern Table (0 to 106)
    // Each string represents 6 widths: bar, space, bar, space, bar, space
    private static $patterns = [
        "212222", "222122", "222221", "121223", "121322", "131222", "122213", "122312", "132212", "221213", // 0-9
        "221312", "231212", "112232", "122132", "122231", "113222", "123122", "123221", "223211", "221132", // 10-19
        "221231", "213212", "223112", "312131", "311222", "321122", "321221", "312212", "322112", "322211", // 20-29
        "212123", "212321", "232121", "111323", "131123", "131321", "112313", "132113", "132311", "211313", // 30-39
        "231113", "231311", "112133", "112331", "132131", "113123", "113321", "133121", "313121", "211331", // 40-49
        "231131", "213113", "213311", "213131", "311123", "311321", "331121", "312113", "312311", "332111", // 50-59
        "314111", "221411", "431111", "111224", "111422", "121124", "121421", "141122", "141221", "112214", // 60-69
        "112412", "122114", "122411", "142112", "142211", "241211", "221114", "413111", "241112", "134111", // 70-79
        "111242", "121142", "121241", "114212", "124112", "124211", "411212", "421112", "421211", "212141", // 80-89
        "214121", "412121", "111143", "111341", "131141", "114113", "114311", "411113", "411311", "113141", // 90-99
        "114131", "311141", "411131", "211412", "211214", "211232", "2331112" // 100-106 (104=StartB, 106=Stop)
    ];

    /**
     * Generate an SVG Code 128 string for the given text.
     *
     * @param string $text The text to encode (e.g. 'DL-22')
     * @param int $height Height of the barcode in px
     * @param int $moduleWidth Width of a single module unit in px
     * @return string SVG XML markup
     */
    public static function getSvg($text, $height = 40, $moduleWidth = 2) {
        $text = (string)$text;
        $len = strlen($text);
        if ($len === 0) return '';

        // Start with Code B (index 104)
        $indices = [104];
        $checksum = 104;

        for ($i = 0; $i < $len; $i++) {
            $ascii = ord($text[$i]);
            // ASCII 32 to 127 maps directly to Code 128 values (ascii - 32)
            $val = ($ascii >= 32 && $ascii <= 126) ? ($ascii - 32) : 0;
            $indices[] = $val;
            $checksum += $val * ($i + 1);
        }

        // Add Checksum
        $checkVal = $checksum % 103;
        $indices[] = $checkVal;

        // Add Stop character (index 106)
        $indices[] = 106;

        // Render bars
        $rects = [];
        $x = 0;

        foreach ($indices as $idx) {
            $pattern = self::$patterns[$idx] ?? '';
            $pLen = strlen($pattern);
            for ($p = 0; $p < $pLen; $p++) {
                $w = (int)$pattern[$p] * $moduleWidth;
                $isBar = ($p % 2 === 0);
                if ($isBar && $w > 0) {
                    $rects[] = "<rect x=\"$x\" y=\"0\" width=\"$w\" height=\"$height\" fill=\"#000000\"/>";
                }
                $x += $w;
            }
        }

        $totalWidth = $x;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $totalWidth . ' ' . $height . '" width="' . $totalWidth . '" height="' . $height . '" preserveAspectRatio="none">' . implode('', $rects) . '</svg>';
    }
}
