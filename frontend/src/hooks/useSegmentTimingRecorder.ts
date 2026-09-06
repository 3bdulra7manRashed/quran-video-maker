'use client';

import React, { useState, useEffect, useRef } from 'react';

export type RecordingState = 'Idle' | 'Loading' | 'Ready' | 'Recording' | 'Importing' | 'Completed' | 'Error';

export interface ApprovedSegment {
  order: number;
  arabic: string;
  translation: string;
  tafsir: string;
}

export interface SegmentTimingRecord {
  start_ms: number;
  end_ms?: number;
}

export interface UseSegmentTimingRecorderProps {
  surahNumber: number;
  reciterSlug: string;
  scope: 'full' | 'single' | 'range';
  ayahNumber: number | null;
  fromAyah: number | null;
  toAyah: number | null;
  approvedSegments: ApprovedSegment[];
  apiUrl: string;
  persistToDatabase?: boolean;
  onRecordingComplete: (json: string, persistToDatabase?: boolean) => Promise<void>;
  onCancel: () => void;
}

export function useSegmentTimingRecorder({
  surahNumber,
  reciterSlug,
  scope,
  ayahNumber,
  fromAyah,
  toAyah,
  approvedSegments,
  apiUrl,
  persistToDatabase = true,
  onRecordingComplete,
  onCancel,
}: UseSegmentTimingRecorderProps) {
  const [recordingState, setRecordingState] = useState<RecordingState>('Idle');
  const [activeIndex, setActiveIndex] = useState<number>(0);
  const [timestamps, setTimestamps] = useState<Record<number, number>>({});
  const [segmentTimings, setSegmentTimings] = useState<Record<number, SegmentTimingRecord>>({});
  const [isPlaying, setIsPlaying] = useState<boolean>(false);
  const [duration, setDuration] = useState<number>(0);
  const [currentTime, setCurrentTime] = useState<number>(0);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // New start/end time boundary states
  const [startTime, setStartTime] = useState<number>(0);
  const [endTime, setEndTime] = useState<number>(0);
  const hasSubmittedRef = useRef<boolean>(false);
  const lastMarkTimeRef = useRef<number>(0);
  const activeIndexRef = useRef<number>(0);
  const segmentTimingsRef = useRef<Record<number, SegmentTimingRecord>>({});

  const audioRef = useRef<HTMLAudioElement | null>(null);

  // Keep activeIndexRef in sync with activeIndex
  const handleSetActiveIndex = React.useCallback((idx: number | ((prev: number) => number)) => {
    setActiveIndex((prev) => {
      const next = typeof idx === 'function' ? idx(prev) : idx;
      activeIndexRef.current = next;
      return next;
    });
  }, []);

  // Deduplicate and strictly sort approved segments sequentially by order
  const sortedSegments = React.useMemo(() => {
    const seen = new Set<number>();
    const result: ApprovedSegment[] = [];
    const raw = [...approvedSegments].sort((a, b) => (a.order ?? (a as any).segment_order ?? 0) - (b.order ?? (b as any).segment_order ?? 0));
    
    for (let i = 0; i < raw.length; i++) {
      const seg = raw[i];
      let order = seg.order ?? (seg as any).segment_order ?? (i + 1);
      if (seen.has(order)) {
        order = i + 1;
      }
      seen.add(order);
      result.push({
        ...seg,
        order,
      });
    }
    return result;
  }, [approvedSegments]);

  // Construct absolute API URL using the provided apiUrl prop
  const audioUrl = `${apiUrl}/api/datasets/audio/stream?reciter=${reciterSlug}&surah=${surahNumber}`;

  // Reset state when audio changes or Surah/Reciter changes
  useEffect(() => {
    setRecordingState('Loading');
    setActiveIndex(0);
    activeIndexRef.current = 0;
    setTimestamps({});
    setSegmentTimings({});
    segmentTimingsRef.current = {};
    setIsPlaying(false);
    setDuration(0);
    setCurrentTime(0);
    setStartTime(0);
    setEndTime(0);
    setErrorMsg(null);
    hasSubmittedRef.current = false;
  }, [reciterSlug, surahNumber]);

  // Audio HTML5 listeners mapping helpers
  const handlePlay = () => {
    setIsPlaying(true);
  };

  const handlePause = () => {
    setIsPlaying(false);
  };

  const handleTimeUpdate = (e: React.SyntheticEvent<HTMLAudioElement>) => {
    const time = e.currentTarget.currentTime;
    setCurrentTime(time);

    // Stop recording and submit when playhead reaches or exceeds endTime
    if (recordingState === 'Recording' && endTime > 0 && time >= endTime && !hasSubmittedRef.current) {
      hasSubmittedRef.current = true;
      pause();

      const currentIdx = activeIndexRef.current;
      const currentSegment = sortedSegments[currentIdx];
      const endMs = Math.round(endTime * 1000);
      const updated: Record<number, SegmentTimingRecord> = { ...segmentTimingsRef.current };
      if (currentSegment) {
        updated[currentSegment.order] = {
          start_ms: updated[currentSegment.order]?.start_ms ?? Math.round(startTime * 1000),
          end_ms: endMs,
        };
      }
      setSegmentTimings(updated);
      segmentTimingsRef.current = updated;

      submitTimings(updated);
    }
  };

  const handleDurationChange = (e: React.SyntheticEvent<HTMLAudioElement>) => {
    const dur = e.currentTarget.duration || 0;
    setDuration(dur);
    setEndTime((prev) => prev === 0 ? dur : prev);
  };

  const handleLoadedMetadata = (e: React.SyntheticEvent<HTMLAudioElement>) => {
    const dur = e.currentTarget.duration || 0;
    setDuration(dur);
    setEndTime(dur);
    setRecordingState('Ready');
    setErrorMsg(null);
  };

  const handleAudioError = () => {
    setRecordingState('Error');
    setErrorMsg('Failed to load audio file from backend server. Please verify the audio file exists.');
  };

  const handleEnded = () => {
    if (recordingState === 'Recording' && !hasSubmittedRef.current) {
      hasSubmittedRef.current = true;
      pause();

      const currentIdx = activeIndexRef.current;
      const currentSegment = sortedSegments[currentIdx];
      const endMs = Math.round((duration || endTime) * 1000);
      const updated: Record<number, SegmentTimingRecord> = { ...segmentTimingsRef.current };
      if (currentSegment) {
        updated[currentSegment.order] = {
          start_ms: updated[currentSegment.order]?.start_ms ?? Math.round(startTime * 1000),
          end_ms: endMs,
        };
      }
      setSegmentTimings(updated);
      segmentTimingsRef.current = updated;

      submitTimings(updated);
    }
  };

  // Playback control functions
  const play = () => {
    if (audioRef.current) {
      audioRef.current.play().catch((err) => {
        console.error('Audio playback failed:', err);
        setRecordingState('Error');
        setErrorMsg(`Audio playback failed: ${err.message}`);
      });
    }
  };

  const pause = () => {
    if (audioRef.current) {
      audioRef.current.pause();
    }
  };

  const togglePlayPause = () => {
    if (isPlaying) {
      pause();
    } else {
      play();
    }
  };

  const startRecording = () => {
    if (recordingState === 'Ready') {
      if (sortedSegments.length === 0) return;

      if (endTime > 0 && startTime >= endTime) {
        setErrorMsg('The End Position must be strictly after the Start Position.');
        return;
      }

      hasSubmittedRef.current = false;

      // Seek to startTime before starting
      if (audioRef.current) {
        audioRef.current.currentTime = startTime;
        setCurrentTime(startTime);
      }

      const initialStartMs = Math.round(startTime * 1000);
      const firstSegOrder = sortedSegments[0].order;

      // Segment 1 starts immediately at initial playback offset (0ms)
      const initialTimings: Record<number, SegmentTimingRecord> = {
        [firstSegOrder]: {
          start_ms: initialStartMs,
        },
      };

      setSegmentTimings(initialTimings);
      segmentTimingsRef.current = initialTimings;
      setTimestamps({ [firstSegOrder]: initialStartMs });
      setRecordingState('Recording');
      setActiveIndex(0);
      activeIndexRef.current = 0;
      play();
    }
  };

  const seek = (seconds: number) => {
    if (audioRef.current) {
      const newTime = Math.max(0, Math.min(duration, audioRef.current.currentTime + seconds));
      audioRef.current.currentTime = newTime;
      setCurrentTime(newTime);
    }
  };

  const seekTo = (seconds: number) => {
    if (audioRef.current) {
      const newTime = Math.max(0, Math.min(duration, seconds));
      audioRef.current.currentTime = newTime;
      setCurrentTime(newTime);
    }
  };

  // Recording operations with 300ms debounce:
  // Spacebar marks the END of current active segment AND START of the next segment.
  const recordTimestamp = React.useCallback(() => {
    if (!audioRef.current || recordingState !== 'Recording') return;

    const now = Date.now();
    if (now - lastMarkTimeRef.current < 300) return; // Prevent double firing within 300ms
    lastMarkTimeRef.current = now;

    const N = sortedSegments.length;
    if (N === 0) return;

    const currentMs = Math.round(audioRef.current.currentTime * 1000);
    const currentIdx = activeIndexRef.current;
    const currentSegment = sortedSegments[currentIdx];
    if (!currentSegment) return;

    // 1. Set end of current active segment
    // 2. If there is a next segment, set its start time immediately to currentMs
    if (currentIdx + 1 < N) {
      const nextSegment = sortedSegments[currentIdx + 1];
      const updated: Record<number, SegmentTimingRecord> = {
        ...segmentTimingsRef.current,
        [currentSegment.order]: {
          start_ms: segmentTimingsRef.current[currentSegment.order]?.start_ms ?? Math.round(startTime * 1000),
          end_ms: currentMs,
        },
        [nextSegment.order]: {
          start_ms: currentMs,
        },
      };

      setSegmentTimings(updated);
      segmentTimingsRef.current = updated;

      setTimestamps((prev) => ({
        ...prev,
        [currentSegment.order]: prev[currentSegment.order] ?? Math.round(startTime * 1000),
        [nextSegment.order]: currentMs,
      }));

      setActiveIndex(currentIdx + 1);
      activeIndexRef.current = currentIdx + 1;
    } else {
      // Last segment completed
      const updated: Record<number, SegmentTimingRecord> = {
        ...segmentTimingsRef.current,
        [currentSegment.order]: {
          start_ms: segmentTimingsRef.current[currentSegment.order]?.start_ms ?? Math.round(startTime * 1000),
          end_ms: currentMs,
        },
      };

      setSegmentTimings(updated);
      segmentTimingsRef.current = updated;
      pause();
      submitTimings(updated);
    }
  }, [recordingState, sortedSegments, startTime]);

  const undoMark = React.useCallback(() => {
    if (recordingState !== 'Recording') return;

    const currentIdx = activeIndexRef.current;
    const N = sortedSegments.length;
    if (N === 0) return;

    if (currentIdx === 0) {
      // On Segment 1, seek back to initial startTime
      if (audioRef.current) {
        audioRef.current.currentTime = startTime;
        setCurrentTime(startTime);
      }
      return;
    }

    const prevIdx = currentIdx - 1;
    const prevSegment = sortedSegments[prevIdx];
    const currentSegment = sortedSegments[currentIdx];

    const updated = { ...segmentTimingsRef.current };
    if (currentSegment) {
      delete updated[currentSegment.order];
    }
    if (prevSegment) {
      updated[prevSegment.order] = {
        start_ms: updated[prevSegment.order]?.start_ms ?? (prevIdx === 0 ? Math.round(startTime * 1000) : 0),
      };
    }

    setSegmentTimings(updated);
    segmentTimingsRef.current = updated;

    setTimestamps((prev) => {
      const copy = { ...prev };
      if (currentSegment) delete copy[currentSegment.order];
      return copy;
    });

    setActiveIndex(prevIdx);
    activeIndexRef.current = prevIdx;

    // Seek slightly back before the previous marker split
    const prevStartMs = updated[prevSegment?.order]?.start_ms ?? Math.round(startTime * 1000);
    const prevStartSec = prevStartMs / 1000;
    const seekTime = Math.max(startTime, audioRef.current ? Math.max(prevStartSec, audioRef.current.currentTime - 2.0) : prevStartSec);
    if (audioRef.current) {
      audioRef.current.currentTime = seekTime;
      setCurrentTime(seekTime);
    }
  }, [recordingState, sortedSegments, startTime]);

  const stepBack = undoMark;

  const navigateNext = () => {
    if (activeIndex < sortedSegments.length - 1) {
      handleSetActiveIndex((prev) => prev + 1);
    }
  };

  const navigatePrev = () => {
    if (activeIndex > 0) {
      handleSetActiveIndex((prev) => prev - 1);
    }
  };

  const submitTimings = async (targetTimings?: Record<number, SegmentTimingRecord>) => {
    setRecordingState('Importing');
    setErrorMsg(null);

    const timingsMap = targetTimings ?? segmentTimingsRef.current;

    let startAyahVal = fromAyah;
    let toAyahVal = toAyah;
    if (scope === 'single') {
      startAyahVal = ayahNumber;
      toAyahVal = ayahNumber;
    }

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

    const payload = {
      surah: surahNumber,
      type: 'segment_timings',
      from_ayah: startAyahVal,
      to_ayah: toAyahVal,
      end_time_ms: Math.round(endTime * 1000),
      persist_to_database: persistToDatabase,
      segments: segmentsPayload,
    };

    try {
      await onRecordingComplete(JSON.stringify(payload, null, 2), persistToDatabase);
      setRecordingState('Completed');
      setTimeout(() => {
        onCancel();
      }, 1500);
    } catch (err: any) {
      setErrorMsg(err.message || 'Auto-import timings failed.');
      setRecordingState('Error');
    }
  };

  const resetRecording = React.useCallback(() => {
    const initialStartMs = Math.round(startTime * 1000);
    const firstSegOrder = sortedSegments[0]?.order ?? 1;

    const initialTimings: Record<number, SegmentTimingRecord> = {
      [firstSegOrder]: {
        start_ms: initialStartMs,
      },
    };

    setSegmentTimings(initialTimings);
    segmentTimingsRef.current = initialTimings;
    setTimestamps({ [firstSegOrder]: initialStartMs });
    setActiveIndex(0);
    activeIndexRef.current = 0;
    setErrorMsg(null);
    hasSubmittedRef.current = false;
    if (audioRef.current) {
      audioRef.current.currentTime = startTime;
      setCurrentTime(startTime);
    }
  }, [startTime, sortedSegments]);

  const resetAll = resetRecording;

  return {
    audioRef,
    audioUrl,
    recordingState,
    activeIndex,
    setActiveIndex: handleSetActiveIndex,
    timestamps,
    segmentTimings,
    approvedSegments: sortedSegments,
    sortedSegments,
    isPlaying,
    duration,
    currentTime,
    errorMsg,
    startTime,
    setStartTime,
    endTime,
    setEndTime,
    play,
    pause,
    togglePlayPause,
    startRecording,
    seek,
    seekTo,
    recordTimestamp,
    undoMark,
    stepBack,
    navigateNext,
    navigatePrev,
    submitTimings,
    resetRecording,
    resetAll,
    // Event listeners to plug into the HTMLAudioElement
    audioEvents: {
      onPlay: handlePlay,
      onPause: handlePause,
      onTimeUpdate: handleTimeUpdate,
      onDurationChange: handleDurationChange,
      onLoadedMetadata: handleLoadedMetadata,
      onError: handleAudioError,
      onEnded: handleEnded,
    },
  };
}
