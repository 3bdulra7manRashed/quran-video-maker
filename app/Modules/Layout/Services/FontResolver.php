<?php

namespace App\Modules\Layout\Services;

use RuntimeException;

class FontResolver
{
    /**
     * Resolve the absolute path of the QCF font file for a given Mushaf page number.
     *
     * @param int $pageNumber
     * @return string Absolute path to the TTF file.
     * @throws RuntimeException If the font file does not exist.
     */
    public function resolve(int $pageNumber): string
    {
        $paddedPage = sprintf('%03d', $pageNumber);
        $baseDir = base_path('fonts');

        // Check for both uppercase and lowercase extensions
        $possiblePaths = [
            $baseDir . DIRECTORY_SEPARATOR . "QCF_P{$paddedPage}.ttf",
            $baseDir . DIRECTORY_SEPARATOR . "QCF_P{$paddedPage}.TTF",
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new RuntimeException("QCF font for page {$pageNumber} (padded: {$paddedPage}) not found in fonts directory.");
    }
}
