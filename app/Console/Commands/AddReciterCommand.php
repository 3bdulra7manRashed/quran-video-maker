<?php

namespace App\Console\Commands;

use App\Modules\Quran\Models\Reciter;
use Illuminate\Console\Command;

class AddReciterCommand extends Command
{
    protected $signature = 'reciter:add 
                            {--slug= : The unique slug of the reciter} 
                            {--arabic= : The Arabic name of the reciter} 
                            {--english= : The English name of the reciter}';

    protected $description = 'Add a custom reciter to the system';

    public function handle(): int
    {
        $slug = $this->option('slug');
        $arabic = $this->option('arabic');
        $english = $this->option('english');

        // Validation
        if (!$slug || !$arabic || !$english) {
            $this->error('All options (--slug, --arabic, --english) are required.');
            return self::FAILURE;
        }

        // Validate uniqueness of slug
        if (Reciter::where('slug', $slug)->exists()) {
            $this->error("A reciter with slug '{$slug}' already exists.");
            return self::FAILURE;
        }

        // Create the reciter
        $reciter = Reciter::create([
            'slug' => $slug,
            'name_arabic' => $arabic,
            'name_english' => $english,
            'source' => 'custom',
            'is_default' => false,
        ]);

        $this->info("Reciter '{$english}' ({$arabic}) added successfully with source: custom.");
        return self::SUCCESS;
    }
}
