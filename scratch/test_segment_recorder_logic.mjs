// Test the logic of Segment Timing Recorder offset and split markers
import assert from 'assert';

console.log('Testing Segment Timing Recorder Logic...');

const sortedSegments = [
  { order: 1, arabic: 'وٱلضحي ١', translation: 'Trans 1', tafsir: 'Tafsir 1' },
  { order: 2, arabic: 'وٱليل اذا سجي ٢', translation: 'Trans 2', tafsir: 'Tafsir 2' },
  { order: 3, arabic: 'ما ودعك ربك وما قلي ٣', translation: 'Trans 3', tafsir: 'Tafsir 3' },
];

let startTime = 0;
let endTime = 9.0;
let segmentTimings = {};
let activeIndex = 0;
let isFinished = false;
let submittedPayload = null;

// 1. startRecording()
function startRecording() {
  const initialStartMs = Math.round(startTime * 1000);
  const firstSegOrder = sortedSegments[0].order;

  segmentTimings = {
    [firstSegOrder]: {
      start_ms: initialStartMs,
    },
  };
  activeIndex = 0;
  isFinished = false;
}

// 2. recordTimestamp(currentMs) [Space key]
function recordTimestamp(currentMs) {
  const N = sortedSegments.length;
  const currentIdx = activeIndex;
  const currentSegment = sortedSegments[currentIdx];
  if (!currentSegment) return;

  if (currentIdx + 1 < N) {
    const nextSegment = sortedSegments[currentIdx + 1];
    segmentTimings = {
      ...segmentTimings,
      [currentSegment.order]: {
        start_ms: segmentTimings[currentSegment.order]?.start_ms ?? Math.round(startTime * 1000),
        end_ms: currentMs,
      },
      [nextSegment.order]: {
        start_ms: currentMs,
      },
    };
    activeIndex = currentIdx + 1;
  } else {
    // Last segment completed
    segmentTimings = {
      ...segmentTimings,
      [currentSegment.order]: {
        start_ms: segmentTimings[currentSegment.order]?.start_ms ?? Math.round(startTime * 1000),
        end_ms: currentMs,
      },
    };
    isFinished = true;
    submitTimings(segmentTimings);
  }
}

// 3. submitTimings()
function submitTimings(timingsMap) {
  const segmentsPayload = sortedSegments.map((seg, idx) => {
    const timing = timingsMap[seg.order];
    const startMs = timing?.start_ms ?? (idx === 0 ? Math.round(startTime * 1000) : 0);

    let endMs = timing?.end_ms;
    if (endMs === undefined) {
      if (idx < sortedSegments.length - 1) {
        const nextTiming = timingsMap[sortedSegments[idx + 1].order];
        endMs = nextTiming?.start_ms ?? (startMs + 3000);
      } else {
        endMs = Math.round(endTime * 1000);
      }
    }

    if (endMs <= startMs) {
      endMs = startMs + 1000;
    }

    return {
      segment_order: seg.order,
      order: seg.order,
      start: startMs,
      start_ms: startMs,
      end: endMs,
      end_ms: endMs,
      duration_ms: endMs - startMs,
      arabic: seg.arabic,
      translation: seg.translation,
      tafsir: seg.tafsir,
    };
  });

  submittedPayload = {
    surah: 93,
    type: 'segment_timings',
    end_time_ms: Math.round(endTime * 1000),
    segments: segmentsPayload,
  };
}

// Run flow simulation:
startRecording();

// Check initial state
assert.strictEqual(activeIndex, 0);
assert.strictEqual(segmentTimings[1].start_ms, 0);
assert.strictEqual(segmentTimings[1].end_ms, undefined);
assert.strictEqual(segmentTimings[2], undefined);
console.log('✓ Initial start: Segment 1 anchored to 0ms immediately.');

// User listens to Ayah 1 and hits [Space] at 3000ms
recordTimestamp(3000);
assert.strictEqual(activeIndex, 1);
assert.strictEqual(segmentTimings[1].start_ms, 0);
assert.strictEqual(segmentTimings[1].end_ms, 3000);
assert.strictEqual(segmentTimings[2].start_ms, 3000);
assert.strictEqual(segmentTimings[2].end_ms, undefined);
console.log('✓ Space at 3000ms: Segment 1 closed [0, 3000], Segment 2 activated at 3000ms.');

// User listens to Ayah 2 and hits [Space] at 6000ms
recordTimestamp(6000);
assert.strictEqual(activeIndex, 2);
assert.strictEqual(segmentTimings[2].start_ms, 3000);
assert.strictEqual(segmentTimings[2].end_ms, 6000);
assert.strictEqual(segmentTimings[3].start_ms, 6000);
assert.strictEqual(segmentTimings[3].end_ms, undefined);
console.log('✓ Space at 6000ms: Segment 2 closed [3000, 6000], Segment 3 activated at 6000ms.');

// User listens to Ayah 3 and hits [Space] at 9000ms (last segment)
recordTimestamp(9000);
assert.strictEqual(isFinished, true);
assert.strictEqual(segmentTimings[3].start_ms, 6000);
assert.strictEqual(segmentTimings[3].end_ms, 9000);
console.log('✓ Space on final segment: Segment 3 closed [6000, 9000] and finished.');

// Check output payload
assert.notStrictEqual(submittedPayload, null);
assert.strictEqual(submittedPayload.segments[0].start, 0);
assert.strictEqual(submittedPayload.segments[0].end, 3000);
assert.strictEqual(submittedPayload.segments[1].start, 3000);
assert.strictEqual(submittedPayload.segments[1].end, 6000);
assert.strictEqual(submittedPayload.segments[2].start, 6000);
assert.strictEqual(submittedPayload.segments[2].end, 9000);
assert.strictEqual(submittedPayload.end_time_ms, 9000);

// Test Undo flow
console.log('Testing Undo functionality...');
startRecording();
recordTimestamp(3000); // Seg 1 ended at 3000, Seg 2 active with start 3000
assert.strictEqual(activeIndex, 1);
assert.strictEqual(segmentTimings[1].end_ms, 3000);
assert.strictEqual(segmentTimings[2].start_ms, 3000);

// Now user realizes they marked too early or wants to redo: press Undo
function undoMark() {
  const currentIdx = activeIndex;
  if (currentIdx === 0) return;
  const prevIdx = currentIdx - 1;
  const prevSegment = sortedSegments[prevIdx];
  const currentSegment = sortedSegments[currentIdx];

  delete segmentTimings[currentSegment.order];
  segmentTimings[prevSegment.order] = {
    start_ms: segmentTimings[prevSegment.order]?.start_ms ?? 0,
  };
  activeIndex = prevIdx;
}

undoMark();
assert.strictEqual(activeIndex, 0);
assert.strictEqual(segmentTimings[1].start_ms, 0);
assert.strictEqual(segmentTimings[1].end_ms, undefined);
assert.strictEqual(segmentTimings[2], undefined);
console.log('✓ Undo successfully re-opened Segment 1 and removed Segment 2 start.');

console.log('All logic assertions passed successfully!');
