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
            [
                'name_arabic' => 'ناصر القطامي',
                'name_english' => 'Nasser Al-Qatami',
                'slug' => 'nasser-al-qatami',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'وديع اليمني',
                'name_english' => 'Wadee Al-Yamani',
                'slug' => 'wadee-al-yamani',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'أحمد النفيس',
                'name_english' => 'Ahmed Al-Nufais',
                'slug' => 'ahmed-an-nafees',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'أحمد بن طالب حميد',
                'name_english' => 'Ahmed Taleb Bin Humaid',
                'slug' => 'ahmed-taleb-bin-humaid',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'أحمد بن علي العجمي',
                'name_english' => 'Ahmed Al-Ajmi',
                'slug' => 'ahmed-al-ajmi',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'إدريس أبكر',
                'name_english' => 'Idrees Abkar',
                'slug' => 'idrees-abbkar',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'أحمد الحذيفي',
                'name_english' => 'Ahmed Al-Huthayfi',
                'slug' => 'ahmed-huthayfi',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'بدر التركي',
                'name_english' => 'Badr Al-Turki',
                'slug' => 'badr-al-turaiqi',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'بندر بليلة',
                'name_english' => 'Bandar Balilah',
                'slug' => 'bandar-balilah',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'خالد الجليل',
                'name_english' => 'Khaled Al-Jalil',
                'slug' => 'khaled-al-jalil',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'رعد الكردي',
                'name_english' => 'Raad Al-Kurdi',
                'slug' => 'raad-al-kurdi',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'زكي داغستاني',
                'name_english' => 'Zaki Daghistani',
                'slug' => 'zaki-daghistani',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'سعد الغامدي',
                'name_english' => 'Saad Al-Ghamdi',
                'slug' => 'saad-al-ghamdi',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'سعود الشريم',
                'name_english' => 'Saud Al-Shuraim',
                'slug' => 'saud-al-shuraim',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'شيرزاد طاهر',
                'name_english' => 'Shirazad Taher',
                'slug' => 'shirazad-taher',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'صالح آل طالب',
                'name_english' => 'Saleh Al-Taleb',
                'slug' => 'saleh-al-taleb',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'عبد الرحمن السديس',
                'name_english' => 'Abdulrahman Al-Sudais',
                'slug' => 'abdulrahman-al-sudais',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'عبد الله الخلف',
                'name_english' => 'Abdullah Al-Khalaf',
                'slug' => 'abdullah-al-khalaf',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'عبد الله المطرود',
                'name_english' => 'Abdullah Al-Matroud',
                'slug' => 'abdullah-almatroud',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'عبد الله القرافي',
                'name_english' => 'Abdullah Al-Qurafi',
                'slug' => 'abdullah-al-qurafi',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'عبد الله الجهني',
                'name_english' => 'Abdullah Al-Juhani',
                'slug' => 'abdullah-al-juhani',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'عبد الله كامل',
                'name_english' => 'Abdullah Kamel',
                'slug' => 'abdullah-kamel',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'عبد الولي الأركاني',
                'name_english' => 'Abdulwali Al-Arkani',
                'slug' => 'abdulwali-al-ardkani',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'فارس عباد',
                'name_english' => 'Fares Abbad',
                'slug' => 'fares-abbad',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'محمد أيوب',
                'name_english' => 'Mohammed Ayyoub',
                'slug' => 'mohammed-ayyoub',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'محمد اللحيدان',
                'name_english' => 'Mohammed Al-Luhaidan',
                'slug' => 'mohammed-al-luhaidan',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'هيثم الدخين',
                'name_english' => 'Haitham Al-Dukhin',
                'slug' => 'haitham-al-dukhain',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'هيثم الجدعاني',
                'name_english' => 'Haitham Al-Jadaani',
                'slug' => 'hiatham-al-jidaani',
                'is_default' => false,
                'source' => 'quran_com',
            ],
            [
                'name_arabic' => 'هزاع البلوشي',
                'name_english' => 'Hazzaa Al-Baloushi',
                'slug' => 'hazzaa-al-baloushi',
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
