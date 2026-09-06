<?php

namespace Database\Seeders;

use App\Modules\Quran\Models\Reciter;
use Illuminate\Database\Seeder;

class ReciterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Remove the test reciter safely without touching any other records
        Reciter::where('name_arabic', 'like', '%القارئ 3%')
            ->orWhere('name_english', 'like', '%Test Reciter 3%')
            ->orWhere('slug', 'test-reciter-3')
            ->delete();

        // 2. Verified reciters with full Word-by-Word timing & audio support
        $reciters = [
            [
                'name_arabic' => 'ياسر الدوسري',
                'name_english' => 'Yasser Al-Dosari',
                'slug' => 'yasser-al-dosari',
                'is_default' => true,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'علي جابر',
                'name_english' => 'Ali Jaber',
                'slug' => 'ali-jaber',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'مشاري راشد العفاسي',
                'name_english' => 'Mishari Rashid Al-Afasy',
                'slug' => 'mishari-al-afasy',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'محمود خليل الحصري',
                'name_english' => 'Mahmoud Khalil Al-Hussary',
                'slug' => 'mahmoud-khalil-al-hussary',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'محمد صديق المنشاوي',
                'name_english' => 'Mohamed Siddiq Al-Minshawi',
                'slug' => 'mohamed-siddiq-al-minshawi',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'عبد الباسط عبد الصمد',
                'name_english' => 'AbdulBaset AbdulSamad',
                'slug' => 'abdul-baset-abdul-samad',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'ماهر المعيقلي',
                'name_english' => 'Maher Al-Muaiqly',
                'slug' => 'maher-al-muaiqly',
                'is_default' => false,
                'source' => 'quran_com',
            ],
        ];

        // Safe upsert using updateOrCreate - never truncates or resets table
        foreach ($reciters as $data) {
            Reciter::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
