<?php

/**
 * QCF_BSML Surah Header Glyph Mappings (Surahs 1 - 114)
 *
 * Each Surah header is composed of:
 * 1. Surah Name Glyph:
 *    - Surahs 1 to 37: Unicode U+FB8D to U+FBB1 (0xFB8C + surahNumber)
 *    - Surahs 38 to 114: Unicode U+FBD3 to U+FC1F (0xFBD3 + (surahNumber - 38))
 * 2. Surah Prefix Glyph ("سُورَةُ"):
 *    - Unicode U+FB8C
 *
 * In GD left-to-right rendering, the string is formatted as:
 *   [Surah Name Glyph] . [Surah Prefix Glyph]
 * which renders as right-to-left calligraphy: "سُورَةُ [الاسم]"
 */

$prefix = mb_chr(0xFB8C, 'UTF-8');
$headers = [];

for ($i = 1; $i <= 114; $i++) {
    if ($i <= 37) {
        $name = mb_chr(0xFB8C + $i, 'UTF-8');
    } else {
        $name = mb_chr(0xFBD3 + ($i - 38), 'UTF-8');
    }
    $headers[$i] = $name . $prefix;
}

return $headers;
