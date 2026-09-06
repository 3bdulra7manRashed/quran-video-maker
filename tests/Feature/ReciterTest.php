<?php

namespace Tests\Feature;

use App\Modules\Quran\Models\Reciter;
use Database\Seeders\ReciterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReciterTest extends TestCase
{
    use RefreshDatabase;

    public function test_reciter_seeder_upserts_verified_reciters_and_removes_test_reciter(): void
    {
        // Insert a dummy test reciter to test cleanup
        Reciter::firstOrCreate(
            ['slug' => 'test-reciter-3'],
            [
                'name_arabic' => 'القارئ 3',
                'name_english' => 'Test Reciter 3',
                'is_default' => false,
                'source' => 'custom',
            ]
        );

        $this->assertDatabaseHas('reciters', ['slug' => 'test-reciter-3']);

        // Run the seeder
        $seeder = new ReciterSeeder();
        $seeder->run();

        // Verify test reciter is deleted
        $this->assertDatabaseMissing('reciters', ['slug' => 'test-reciter-3']);

        // Verify verified reciters are present
        $expectedSlugs = [
            'yasser-al-dosari',
            'ali-jaber',
            'mishari-al-afasy',
            'mahmoud-khalil-al-hussary',
            'mohamed-siddiq-al-minshawi',
            'abdul-baset-abdul-samad',
            'maher-al-muaiqly',
        ];

        foreach ($expectedSlugs as $slug) {
            $this->assertDatabaseHas('reciters', [
                'slug' => $slug,
                'source' => 'quran_com',
            ]);
        }
    }

    public function test_api_reciters_endpoint_returns_verified_reciters_without_test_reciter(): void
    {
        $this->seed(ReciterSeeder::class);

        $response = $this->getJson('/api/reciters');
        $response->assertStatus(200);

        $slugs = collect($response->json())->pluck('slug')->all();

        $this->assertNotContains('test-reciter-3', $slugs);
        $this->assertContains('mishari-al-afasy', $slugs);
        $this->assertContains('mahmoud-khalil-al-hussary', $slugs);
        $this->assertContains('mohamed-siddiq-al-minshawi', $slugs);
        $this->assertContains('abdul-baset-abdul-samad', $slugs);
        $this->assertContains('maher-al-muaiqly', $slugs);
    }
}
