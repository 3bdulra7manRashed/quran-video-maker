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
        ];

        foreach ($reciters as $data) {
            Reciter::firstOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
