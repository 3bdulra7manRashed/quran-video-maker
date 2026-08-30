'use client';

import React, { useState, useEffect, useRef } from 'react';

export type RecordingState = 'Idle' | 'Loading' | 'Ready' | 'Recording' | 'Importing' | 'Completed' | 'Error';

export interface ApprovedSegment {
  order: number;
  arabic: string;
  translation: string;
  tafsir: string;
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
  persistToDatabase = false,
  onRecordingComplete,
  onCancel,
}: UseSegmentTimingRecorderProps) {
  const [recordingState, setRecordingState] = useState<RecordingState>('Idle');
  const [activeIndex, setActiveIndex] = useState<number>(0);
  const [timestamps, setTimestamps] = useState<Record<number, number>>({});
  const [isPlaying, setIsPlaying] = useState<boolean>(false);
  const [duration, setDuration] = useState<number>(0);
  const [currentTime, setCurrentTime] = useState<number>(0);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // New start/end time boundary states
  const [startTime, setStartTime] = useState<number>(0);
  const [endTime, setEndTime] = useState<number>(0);
  const hasSubmittedRef = useRef<boolean>(false);
  const lastMarkTimeRef = useRef<number>(0);

  const audioRef = useRef<HTMLAudioElement | null>(null);

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
    setTimestamps({});
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
      submitTimings();
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
      submitTimings(timestamps);
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

      // Start with completely empty timestamps - never auto-stamp segment 1
      setTimestamps({});
      setRecordingState('Recording');
      setActiveIndex(0); // Keep Segment 1 displayed
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

  // Recording operations with 300ms debounce: records timestamp for current segment and advances strictly by 1
  const recordTimestamp = React.useCallback(() => {
    if (!audioRef.current || recordingState !== 'Recording') return;

    const now = Date.now();
    if (now - lastMarkTimeRef.current < 300) return; // Prevent double firing within 300ms
    lastMarkTimeRef.current = now;

    const N = sortedSegments.length;
    if (N === 0) return;

    const currentMs = Math.round(audioRef.current.currentTime * 1000);

    setActiveIndex((currentIdx) => {
      const currentSegment = sortedSegments[currentIdx];
      if (currentSegment) {
        setTimestamps((prev) => ({
          ...prev,
          [currentSegment.order]: currentMs,
        }));
      }

      if (currentIdx < N - 1) {
        return currentIdx + 1;
      }
      return currentIdx;
    });
  }, [recordingState, sortedSegments]);

  const undoMark = React.useCallback(() => {
    if (recordingState !== 'Recording') return;

    const N = sortedSegments.length;
    if (N === 0) return;

    setActiveIndex((currentIdx) => {
      const currentActiveSeg = sortedSegments[currentIdx];
      const currentSegOrder = currentActiveSeg?.order;

      const hasCurrentTimestamp = currentSegOrder !== undefined && timestamps[currentSegOrder] !== undefined;

      let targetIndex: number;
      if (hasCurrentTimestamp) {
        targetIndex = currentIdx;
      } else if (currentIdx > 0) {
        targetIndex = currentIdx - 1;
      } else {
        return currentIdx;
      }

      const segToDelete = sortedSegments[targetIndex];
      if (segToDelete) {
        setTimestamps((prev) => {
          const updated = { ...prev };
          delete updated[segToDelete.order];
          return updated;
        });
      }

      const newIndex = Math.max(0, targetIndex);

      const prevSegOrder = sortedSegments[Math.max(0, newIndex - 1)]?.order;
      const prevTimeMs = prevSegOrder !== undefined ? timestamps[prevSegOrder] : undefined;
      const targetSec = prevTimeMs !== undefined ? prevTimeMs / 1000 : startTime;
      const seekTime = Math.max(startTime, targetSec > startTime ? targetSec - 0.3 : startTime);

      if (audioRef.current) {
        audioRef.current.currentTime = seekTime;
        setCurrentTime(seekTime);
      }

      return newIndex;
    });
  }, [recordingState, sortedSegments, timestamps, startTime]);

  const stepBack = undoMark;

  const navigateNext = () => {
    if (activeIndex < sortedSegments.length - 1) {
      setActiveIndex((prev) => prev + 1);
    }
  };

  const navigatePrev = () => {
    if (activeIndex > 0) {
      setActiveIndex((prev) => prev - 1);
    }
  };

  const submitTimings = async (targetTimestamps: Record<number, number> = timestamps) => {
    setRecordingState('Importing');
    setErrorMsg(null);

    let startAyahVal = fromAyah;
    let toAyahVal = toAyah;
    if (scope === 'single') {
      startAyahVal = ayahNumber;
      toAyahVal = ayahNumber;
    }

    const segmentsPayload = sortedSegments.map((seg) => ({
      segment_order: seg.order,
      order: seg.order,
      start: targetTimestamps[seg.order] ?? 0,
      arabic: seg.arabic,
      translation: seg.translation,
      tafsir: seg.tafsir,
    }));

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
    setTimestamps({});
    setActiveIndex(0);
    setErrorMsg(null);
    hasSubmittedRef.current = false;
    if (audioRef.current) {
      audioRef.current.currentTime = startTime;
      setCurrentTime(startTime);
    }
  }, [startTime]);

  const resetAll = resetRecording;

  return {
    audioRef,
    audioUrl,
    recordingState,
    activeIndex,
    setActiveIndex,
    timestamps,
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
