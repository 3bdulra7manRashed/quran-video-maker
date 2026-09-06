<?php

namespace Tests\Feature;

use App\Jobs\PrepareDatasetJob;
use App\Modules\Dataset\Providers\DatasetProvider;
use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\Surah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class DatasetPrepareTest extends TestCase
{
    use RefreshDatabase;

    protected Reciter $reciter;
    protected Surah $surah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reciter = Reciter::create([
            'name_english' => 'Yasser Al-Dosari',
            'name_arabic' => 'ياسر الدوسري',
            'slug' => 'yasser-al-dosari',
            'source' => 'default',
        ]);

        $this->surah = Surah::create([
            'number' => 12,
            'name_arabic' => 'يوسف',
            'name_simple' => 'Yusuf',
            'verses_count' => 111,
            'start_page' => 235,
            'end_page' => 248,
        ]);
    }

    public function test_validation_fails_when_fields_missing(): void
    {
        $response = $this->postJson('/api/datasets/prepare', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reciter', 'surah']);
    }

    public function test_validation_fails_for_non_existent_reciter_or_surah(): void
    {
        $response = $this->postJson('/api/datasets/prepare', [
            'reciter' => 'non-existent-reciter',
            'surah' => 999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reciter', 'surah']);
    }

    public function test_prepare_dispatches_job_and_returns_processing_status(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/datasets/prepare', [
            'reciter' => 'yasser-al-dosari',
            'surah' => 12,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'processing',
        ]);

        Queue::assertPushed(PrepareDatasetJob::class, function ($job) {
            $reflection = new \ReflectionClass($job);
            
            $reciterProp = $reflection->getProperty('reciterSlug');
            $reciterProp->setAccessible(true);
            $reciter = $reciterProp->getValue($job);

            $surahProp = $reflection->getProperty('surahNumber');
            $surahProp->setAccessible(true);
            $surah = $surahProp->getValue($job);

            return $reciter === 'yasser-al-dosari' && $surah === 12 && $job->timeout === 0;
        });
    }

    public function test_prepare_job_executes_provider_steps(): void
    {
        $mockProvider = Mockery::mock(DatasetProvider::class);
        $mockProvider->shouldReceive('downloadGlyphs')->zeroOrMoreTimes()->with(12);
        $mockProvider->shouldReceive('importGlyphs')->atLeast()->once()->with(12);
        $mockProvider->shouldReceive('determineGlyphsAvailability')->zeroOrMoreTimes()->with(12)->andReturn(false);
        $mockProvider->shouldReceive('determineAudioAvailability')->once()->with('yasser-al-dosari', 12)->andReturn(true);
        $mockProvider->shouldReceive('determineTimingsAvailability')->once()->with('yasser-al-dosari', 12)->andReturn(true);

        $job = new PrepareDatasetJob('yasser-al-dosari', 12);
        $this->assertSame(0, $job->timeout);

        // Ensure handle runs cleanly without error
        $job->handle($mockProvider);
    }
}
