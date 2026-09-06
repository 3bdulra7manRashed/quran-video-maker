<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Shared\Services\QuranPathResolver;
use Illuminate\Support\Facades\Log;

class BackgroundFactory
{
    protected QuranPathResolver $pathResolver;

    public function __construct(QuranPathResolver $pathResolver)
    {
        $this->pathResolver = $pathResolver;
    }

    /**
     * Apply the configured background to the given GD canvas.
     *
     * @param \GdImage $im
     * @param array $layoutData
     * @return void
     */
    public function apply(\GdImage $im, array $layoutData = []): void
    {
        $width = imagesx($im);
        $height = imagesy($im);
        $theme = $layoutData['theme'] ?? '';

        // When theme is quran_me, mount the static background template
        if ($theme === 'quran_me') {
            $candidatePaths = [
                function_exists('public_path') ? public_path('templates/quran_me_bg.jpg') : null,
                function_exists('storage_path') ? storage_path('app/public/templates/quran_me_bg.jpg') : null,
                base_path('public/templates/quran_me_bg.jpg'),
                base_path('storage/app/public/templates/quran_me_bg.jpg'),
            ];

            foreach ($candidatePaths as $bgPath) {
                if ($bgPath && file_exists($bgPath)) {
                    $baseImage = @imagecreatefromjpeg($bgPath);
                    if (!$baseImage) {
                        $baseImage = @imagecreatefrompng($bgPath);
                    }
                    if ($baseImage) {
                        imagecopyresampled($im, $baseImage, 0, 0, 0, 0, $width, $height, imagesx($baseImage), imagesy($baseImage));
                        imagedestroy($baseImage);
                        return;
                    }
                }
            }

            Log::warning("[BackgroundFactory] quran_me template background image not found. Falling back to default background.");
        }

        // Load configuration
        $config = config('layouts.reels.background', [
            'type' => 'solid',
            'color' => '#000000',
        ]);

        $type = $config['type'] ?? 'solid';

        if ($type === 'solid') {
            $colorHex = $config['color'] ?? '#000000';

            // Validate Hex color code
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex)) {
                Log::warning("[BackgroundFactory] Invalid HEX color code '{$colorHex}' in configuration. Falling back to '#000000'.");
                $colorHex = '#000000';
            }

            // Parse hex color values
            $hex = ltrim($colorHex, '#');
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            // Draw solid background color
            $colorAllocated = imagecolorallocate($im, $r, $g, $b);
            imagefilledrectangle($im, 0, 0, $width, $height, $colorAllocated);
        } else {
            // Default image fallback (or if type = 'image')
            $imageName = $config['path'] ?? 'default.png';
            $backgroundPath = $this->pathResolver->backgrounds($imageName);

            if (!file_exists($backgroundPath)) {
                Log::warning("[BackgroundFactory] Background image file '{$backgroundPath}' not found. Falling back to solid black.");
                $colorAllocated = imagecolorallocate($im, 0, 0, 0);
                imagefilledrectangle($im, 0, 0, $width, $height, $colorAllocated);
                return;
            }

            $bg = imagecreatefrompng($backgroundPath);
            if ($bg) {
                imagecopy($im, $bg, 0, 0, 0, 0, $width, $height);
                imagedestroy($bg);
            } else {
                Log::error("[BackgroundFactory] Failed to load background image from '{$backgroundPath}'. Falling back to solid black.");
                $colorAllocated = imagecolorallocate($im, 0, 0, 0);
                imagefilledrectangle($im, 0, 0, $width, $height, $colorAllocated);
            }
        }
    }
}
