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
  onRecordingComplete: (json: string) => Promise<void>;
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

  const audioRef = useRef<HTMLAudioElement | null>(null);

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
    setErrorMsg(null);
  }, [reciterSlug, surahNumber]);

  // Audio HTML5 listeners mapping helpers
  const handlePlay = () => {
    setIsPlaying(true);
  };

  const handlePause = () => {
    setIsPlaying(false);
  };

  const handleTimeUpdate = (e: React.SyntheticEvent<HTMLAudioElement>) => {
    setCurrentTime(e.currentTarget.currentTime);
  };

  const handleDurationChange = (e: React.SyntheticEvent<HTMLAudioElement>) => {
    setDuration(e.currentTarget.duration || 0);
  };

  const handleLoadedMetadata = (e: React.SyntheticEvent<HTMLAudioElement>) => {
    setDuration(e.currentTarget.duration || 0);
    setRecordingState('Ready');
    setErrorMsg(null);
  };

  const handleAudioError = () => {
    setRecordingState('Error');
    setErrorMsg('Failed to load audio file from backend server. Please verify the audio file exists.');
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
      const currentMs = Math.round((audioRef.current?.currentTime || 0) * 1000);
      const firstSegment = approvedSegments[0];
      const initialTimestamps = firstSegment
        ? { [firstSegment.order]: currentMs }
        : {};

      setTimestamps(initialTimestamps);
      setRecordingState('Recording');
      play();

      if (approvedSegments.length > 1) {
        setActiveIndex(1); // Start from Segment 2 (index 1)
      } else {
        // If there's only 1 segment in total, immediately complete
        pause();
        submitTimings(initialTimestamps);
      }
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

  // Recording operations
  const recordTimestamp = () => {
    if (!audioRef.current || recordingState !== 'Recording') return;
    
    const activeSegment = approvedSegments[activeIndex];
    if (!activeSegment) return;

    const currentMs = Math.round(audioRef.current.currentTime * 1000);
    const updatedTimestamps = {
      ...timestamps,
      [activeSegment.order]: currentMs,
    };
    
    setTimestamps(updatedTimestamps);

    // If it's the last segment, finalize and trigger import
    if (activeIndex === approvedSegments.length - 1) {
      pause();
      submitTimings(updatedTimestamps);
    } else {
      // Auto advance
      setActiveIndex((prev) => prev + 1);
    }
  };

  const stepBack = () => {
    if (recordingState !== 'Recording') return;

    const activeSegment = approvedSegments[activeIndex];
    if (!activeSegment) return;

    if (activeIndex > 1) {
      const prevIndex = activeIndex - 1;
      const prevSegment = approvedSegments[prevIndex];
      setTimestamps((prev) => {
        const updated = { ...prev };
        delete updated[prevSegment.order];
        delete updated[activeSegment.order];
        return updated;
      });
      setActiveIndex(prevIndex);
    } else {
      // If at index 1 (Segment 2), clear Segment 2 timestamp, but keep activeIndex = 1
      setTimestamps((prev) => {
        const updated = { ...prev };
        delete updated[activeSegment.order];
        return updated;
      });
    }
  };

  const navigateNext = () => {
    if (activeIndex < approvedSegments.length - 1) {
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

    const segmentsPayload = approvedSegments.map((seg) => ({
      segment_order: seg.order,
      start: targetTimestamps[seg.order] ?? 0,
    }));

    const payload = {
      surah: surahNumber,
      type: 'segment_timings',
      from_ayah: startAyahVal,
      to_ayah: toAyahVal,
      segments: segmentsPayload,
    };

    try {
      await onRecordingComplete(JSON.stringify(payload, null, 2));
      setRecordingState('Completed');
    } catch (err: any) {
      setErrorMsg(err.message || 'Auto-import timings failed.');
      setRecordingState('Error');
    }
  };

  const resetRecording = () => {
    setTimestamps({});
    setActiveIndex(0);
    setRecordingState('Ready');
    setErrorMsg(null);
    if (audioRef.current) {
      audioRef.current.currentTime = 0;
    }
  };

  return {
    audioRef,
    audioUrl,
    recordingState,
    activeIndex,
    setActiveIndex,
    timestamps,
    isPlaying,
    duration,
    currentTime,
    errorMsg,
    play,
    pause,
    togglePlayPause,
    startRecording,
    seek,
    seekTo,
    recordTimestamp,
    stepBack,
    navigateNext,
    navigatePrev,
    submitTimings,
    resetRecording,
    // Event listeners to plug into the HTMLAudioElement
    audioEvents: {
      onPlay: handlePlay,
      onPause: handlePause,
      onTimeUpdate: handleTimeUpdate,
      onDurationChange: handleDurationChange,
      onLoadedMetadata: handleLoadedMetadata,
      onError: handleAudioError,
    },
  };
}
