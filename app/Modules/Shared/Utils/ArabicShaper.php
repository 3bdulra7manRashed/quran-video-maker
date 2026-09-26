<?php

namespace App\Modules\Shared\Utils;

class ArabicShaper
{
    // Arabic Unicode character ranges and their four forms: [isolated, final, initial, medial]
    private static array $map = [
        // Alef
        "\u{0627}" => ["\u{FE8D}", "\u{FE8E}", "\u{FE8D}", "\u{FE8E}"],
        // Beh
        "\u{0628}" => ["\u{FE8F}", "\u{FE90}", "\u{FE91}", "\u{FE92}"],
        // Teh
        "\u{062A}" => ["\u{FE95}", "\u{FE96}", "\u{FE97}", "\u{FE98}"],
        // Theh
        "\u{062B}" => ["\u{FE99}", "\u{FE9A}", "\u{FE9B}", "\u{FE9C}"],
        // Jeem
        "\u{062C}" => ["\u{FE9D}", "\u{FE9E}", "\u{FE9F}", "\u{FEA0}"],
        // Hah
        "\u{062D}" => ["\u{FEA1}", "\u{FEA2}", "\u{FEA3}", "\u{FEA4}"],
        // Khav
        "\u{062E}" => ["\u{FEA5}", "\u{FEA6}", "\u{FEA7}", "\u{FEA8}"],
        // Dal
        "\u{062F}" => ["\u{FEA9}", "\u{FEAA}", "\u{FEA9}", "\u{FEAA}"],
        // Thal
        "\u{0630}" => ["\u{FEAB}", "\u{FEAC}", "\u{FEAB}", "\u{FEAC}"],
        // Reh
        "\u{0631}" => ["\u{FEAD}", "\u{FEAE}", "\u{FEAD}", "\u{FEAE}"],
        // Zain
        "\u{0632}" => ["\u{FEAF}", "\u{FEB0}", "\u{FEAF}", "\u{FEB0}"],
        // Seen
        "\u{0633}" => ["\u{FEB1}", "\u{FEB2}", "\u{FEB3}", "\u{FEB4}"],
        // Sheen
        "\u{0634}" => ["\u{FEB5}", "\u{FEB6}", "\u{FEB7}", "\u{FEB8}"],
        // Sad
        "\u{0635}" => ["\u{FEB9}", "\u{FEBA}", "\u{FEBB}", "\u{FEBC}"],
        // Dad
        "\u{0636}" => ["\u{FEBD}", "\u{FEBE}", "\u{FEBF}", "\u{FEC0}"],
        // Tah
        "\u{0637}" => ["\u{FEC1}", "\u{FEC2}", "\u{FEC3}", "\u{FEC4}"],
        // Zah
        "\u{0638}" => ["\u{FEC5}", "\u{FEC6}", "\u{FEC7}", "\u{FEC8}"],
        // Ain
        "\u{0639}" => ["\u{FEC9}", "\u{FECA}", "\u{FECB}", "\u{FECC}"],
        // Ghain
        "\u{063A}" => ["\u{FECD}", "\u{FECE}", "\u{FECF}", "\u{FED0}"],
        // Feh
        "\u{0641}" => ["\u{FED1}", "\u{FED2}", "\u{FED3}", "\u{FED4}"],
        // Qaf
        "\u{0642}" => ["\u{FED5}", "\u{FED6}", "\u{FED7}", "\u{FED8}"],
        // Kaf
        "\u{0643}" => ["\u{FED9}", "\u{FEDA}", "\u{FEDB}", "\u{FEDC}"],
        // Lam
        "\u{0644}" => ["\u{FEDD}", "\u{FEDE}", "\u{FEDF}", "\u{FEE0}"],
        // Meem
        "\u{0645}" => ["\u{FEE1}", "\u{FEE2}", "\u{FEE3}", "\u{FEE4}"],
        // Noon
        "\u{0646}" => ["\u{FEE5}", "\u{FEE6}", "\u{FEE7}", "\u{FEE8}"],
        // Heh
        "\u{0647}" => ["\u{FEE9}", "\u{FEEA}", "\u{FEEB}", "\u{FEEC}"],
        // Waw
        "\u{0648}" => ["\u{FEED}", "\u{FEEE}", "\u{FEED}", "\u{FEEE}"],
        // Yeh
        "\u{064A}" => ["\u{FEF1}", "\u{FEF2}", "\u{FEF3}", "\u{FEF4}"],
        // Alif Maqsurah
        "\u{0649}" => ["\u{FEEF}", "\u{FEF0}", "\u{FEEF}", "\u{FEF0}"],
        // Hamza
        "\u{0621}" => ["\u{FE80}", "\u{FE80}", "\u{FE80}", "\u{FE80}"],
        // Alif with Hamza Above
        "\u{0623}" => ["\u{FE83}", "\u{FE84}", "\u{FE83}", "\u{FE84}"],
        // Waw with Hamza Above
        "\u{0624}" => ["\u{FE85}", "\u{FE86}", "\u{FE85}", "\u{FE86}"],
        // Alif with Hamza Below
        "\u{0625}" => ["\u{FE87}", "\u{FE88}", "\u{FE87}", "\u{FE88}"],
        // Yeh with Hamza Above
        "\u{0626}" => ["\u{FE89}", "\u{FE8A}", "\u{FE8B}", "\u{FE8C}"],
        // Teh Marbuta
        "\u{0629}" => ["\u{FE93}", "\u{FE94}", "\u{FE93}", "\u{FE94}"],
        // Alef with Madda Above
        "\u{0622}" => ["\u{FE81}", "\u{FE82}", "\u{FE81}", "\u{FE82}"],
        // Alef Wasla
        "\u{0671}" => ["\u{FB50}", "\u{FB51}", "\u{FB50}", "\u{FB51}"],
    ];

    // Characters that do NOT connect to their left (cannot have initial or medial form on their left)
    private static array $rightConnectingOnly = [
        "\u{0621}", "\u{0622}", "\u{0623}", "\u{0624}", "\u{0625}", "\u{0627}", "\u{062f}", "\u{0630}", "\u{0631}", "\u{0632}", "\u{0648}", "\u{0649}", "\u{0629}", "\u{0671}"
    ];

    /**
     * Map of raw base Unicode Arabic characters (0x0600-0x06FF) to their
     * explicit isolated Presentation Forms-B (and Forms-A) equivalents.
     * ArPHP outputs raw codepoints for isolated forms (form 0); this replaces
     * them with proper presentation glyphs so GD/FreeType renders the intended shape.
     */
    public static array $isolatedFormFixes = [
        // Non-connecting / right-connecting only letters
        "\u{0621}" => "\u{FE80}", // Hamza (ء)
        "\u{0622}" => "\u{FE81}", // Alef with Madda Above (آ)
        "\u{0623}" => "\u{FE83}", // Alef with Hamza Above (أ)
        "\u{0624}" => "\u{FE85}", // Waw with Hamza Above (ؤ)
        "\u{0625}" => "\u{FE87}", // Alef with Hamza Below (إ)
        "\u{0627}" => "\u{FE8D}", // Alef (ا)
        "\u{062F}" => "\u{FEA9}", // Dal (د)
        "\u{0630}" => "\u{FEAB}", // Thal (ذ)
        "\u{0631}" => "\u{FEAD}", // Reh (ر)
        "\u{0632}" => "\u{FEAF}", // Zain (ز)
        "\u{0648}" => "\u{FEED}", // Waw (و)
        "\u{0629}" => "\u{FE93}", // Teh Marbuta (ة)
        "\u{0649}" => "\u{FEEF}", // Alef Maksura (ى)
        "\u{0671}" => "\u{FB50}", // Alef Wasla (ٱ)

        // Dual-connecting letters (in isolated position)
        "\u{0626}" => "\u{FE89}", // Yeh with Hamza Above (ئ)
        "\u{0628}" => "\u{FE8F}", // Beh (ب)
        "\u{062A}" => "\u{FE95}", // Teh (ت)
        "\u{062B}" => "\u{FE99}", // Theh (ث)
        "\u{062C}" => "\u{FE9D}", // Jeem (ج)
        "\u{062D}" => "\u{FEA1}", // Hah (ح)
        "\u{062E}" => "\u{FEA5}", // Khah (خ)
        "\u{0633}" => "\u{FEB1}", // Seen (س)
        "\u{0634}" => "\u{FEB5}", // Sheen (ش)
        "\u{0635}" => "\u{FEB9}", // Sad (ص)
        "\u{0636}" => "\u{FEBD}", // Dad (ض)
        "\u{0637}" => "\u{FEC1}", // Tah (ط)
        "\u{0638}" => "\u{FEC5}", // Zah (ظ)
        "\u{0639}" => "\u{FEC9}", // Ain (ع)
        "\u{063A}" => "\u{FECD}", // Ghain (غ)
        "\u{0641}" => "\u{FED1}", // Feh (ف)
        "\u{0642}" => "\u{FED5}", // Qaf (ق)
        "\u{0643}" => "\u{FED9}", // Kaf (ك)
        "\u{0644}" => "\u{FEDD}", // Lam (ل)
        "\u{0645}" => "\u{FEE1}", // Meem (م)
        "\u{0646}" => "\u{FEE5}", // Noon (ن)
        "\u{0647}" => "\u{FEE9}", // Heh (ه)
        "\u{064A}" => "\u{FEF1}", // Yeh (ي)
    ];

    /**
     * Replace raw base Arabic codepoints in shaped text with their explicit
     * isolated presentation form glyphs.
     */
    public static function fixIsolatedForms(string $shaped): string
    {
        return strtr($shaped, self::$isolatedFormFixes);
    }

    /**
     * Shape and reverse Arabic text for drawing in standard LTR environments like GD.
     *
     * @param string $text UTF-8 string.
     * @return string
     */
    public static function shapeAndReverse(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $numChars = count($chars);
        $shaped = [];

        for ($i = 0; $i < $numChars; $i++) {
            $char = $chars[$i];

            if (!isset(self::$map[$char])) {
                $shaped[] = $char;
                continue;
            }

            // Check connection right
            $prev = ($i > 0) ? $chars[$i - 1] : null;
            $prevConnects = false;
            if ($prev && isset(self::$map[$prev]) && !in_array($prev, self::$rightConnectingOnly, true)) {
                $prevConnects = true;
            }

            // Check connection left
            $next = ($i < $numChars - 1) ? $chars[$i + 1] : null;
            $nextConnects = false;
            if ($next && isset(self::$map[$next])) {
                $nextConnects = true;
            }

            if ($prevConnects && $nextConnects && !in_array($char, self::$rightConnectingOnly, true)) {
                $form = 3; // Medial
            } elseif ($prevConnects) {
                $form = 1; // Final
            } elseif ($nextConnects && !in_array($char, self::$rightConnectingOnly, true)) {
                $form = 2; // Initial
            } else {
                $form = 0; // Isolated
            }

            $shaped[] = self::$map[$char][$form];
        }

        // Reverse for LTR drawing
        return implode('', array_reverse($shaped));
    }
}
