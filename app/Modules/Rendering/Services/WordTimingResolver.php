<?php

namespace App\Modules\Rendering\Services;

use App\Modules\Quran\Models\Reciter;
use App\Modules\Quran\Models\ReciterWordTiming;

class WordTimingResolver
{
    protected array $customTimings = [];

    /**
     * Inject custom timings.
     *
     * @param array $customTimings
     * @return void
     */
    public function setCustomTimings(array $customTimings): void
    {
        $this->customTimings = $customTimings;
    }

    /**
     * Batch resolve timings from reciter_word_timings for the given words and reciter.
     *
     * @param Reciter $reciter
     * @param iterable $words
     * @return array<int, array{start_ms: int, end_ms: int}>
     */
    public function resolve(Reciter $reciter, iterable $words): array
    {
        if (!empty($this->customTimings)) {
            $customMap = [];
            $spokenWords = [];
            foreach ($words as $w) {
                if ($w->char_type === 'word') {
                    $spokenWords[] = $w;
                }
            }
            $index = 0;
            foreach ($words as $w) {
                if ($w->char_type === 'word') {
                    if (isset($this->customTimings[$index])) {
                        $customMap[$w->id] = [
                            'start_ms' => (int)$this->customTimings[$index]['start'],
                            'end_ms' => (int)$this->customTimings[$index]['end'],
                        ];
                    } else {
                        $customMap[$w->id] = [
                            'start_ms' => null,
                            'end_ms' => null,
                        ];
                    }
                    $index++;
                } else {
                    $customMap[$w->id] = [
                        'start_ms' => null,
                        'end_ms' => null,
                    ];
                }
            }
            return $customMap;
        }

        $wordIds = [];
        foreach ($words as $w) {
            $wordIds[] = $w->id;
        }

        if (empty($wordIds)) {
            return [];
        }

        $records = ReciterWordTiming::where('reciter_id', $reciter->id)
            ->whereIn('word_id', $wordIds)
            ->get()
            ->keyBy('word_id');

        $map = [];
        foreach ($words as $w) {
            if ($records->has($w->id)) {
                $record = $records->get($w->id);
                $map[$w->id] = [
                    'start_ms' => $record->start_ms,
                    'end_ms' => $record->end_ms,
                ];
            } else {
                $map[$w->id] = [
                    'start_ms' => null,
                    'end_ms' => null,
                ];
            }
        }

        return $map;
    }
}
