<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SurahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $targetFile = storage_path('app/quran/chapters.json');

        if (!File::exists($targetFile)) {
            File::ensureDirectoryExists(dirname($targetFile));
            
            $response = Http::timeout(30)->get('https://api.quran.com/api/v4/chapters?language=en');
            if ($response->failed()) {
                throw new RuntimeException('Failed to download chapters.json from Quran.com API.');
            }

            File::put($targetFile, $response->body());
        }

        Artisan::call('import:surahs');
    }
}
