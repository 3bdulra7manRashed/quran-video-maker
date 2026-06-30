<?php

namespace App\Modules\Shared\Services;

use Illuminate\Support\Facades\File;

class QuranPathResolver
{
    /**
     * Resolve path for audio directory/files.
     */
    public function audio(string $subPath = ''): string
    {
        $root = env('QURAN_STORAGE_PATH');
        $path = $root 
            ? rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'audio'
            : storage_path('app/quran/audio');

        return $this->resolvePath($path, $subPath);
    }

    /**
     * Resolve path for rendered segments directory/files.
     */
    public function renderedSegments(string $subPath = ''): string
    {
        $root = env('QURAN_STORAGE_PATH');
        $path = $root 
            ? rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'rendered_segments'
            : storage_path('app/rendered_segments');

        return $this->resolvePath($path, $subPath);
    }

    /**
     * Resolve path for compiled videos directory/files.
     */
    public function videos(string $subPath = ''): string
    {
        $root = env('QURAN_STORAGE_PATH');
        $path = $root 
            ? rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'videos'
            : storage_path('app/output');

        return $this->resolvePath($path, $subPath);
    }

    /**
     * Resolve path for debug JSON files.
     */
    public function debug(string $subPath = ''): string
    {
        $root = env('QURAN_STORAGE_PATH');
        $path = $root 
            ? rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'debug'
            : storage_path('app/debug');

        return $this->resolvePath($path, $subPath);
    }

    /**
     * Resolve path for temporary files (e.g. FFmpeg list.txt).
     */
    public function temp(string $subPath = ''): string
    {
        $root = env('QURAN_STORAGE_PATH');
        $path = $root 
            ? rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'temp'
            : storage_path('app/temp');

        return $this->resolvePath($path, $subPath);
    }

    /**
     * Resolve path for background assets (e.g. default.png).
     */
    public function backgrounds(string $subPath = ''): string
    {
        $root = env('QURAN_STORAGE_PATH');
        $path = $root 
            ? rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'backgrounds'
            : storage_path('app/backgrounds');

        return $this->resolvePath($path, $subPath);
    }

    /**
     * Helper to clean up, append subpaths, and ensure target directories exist.
     */
    protected function resolvePath(string $baseDir, string $subPath): string
    {
        $fullPath = $subPath 
            ? $baseDir . DIRECTORY_SEPARATOR . ltrim($subPath, '/\\') 
            : $baseDir;

        // Determine directory to ensure exists
        $dirToCreate = $subPath ? dirname($fullPath) : $fullPath;

        if (!empty($dirToCreate)) {
            File::ensureDirectoryExists($dirToCreate);
        }

        return $fullPath;
    }
}
