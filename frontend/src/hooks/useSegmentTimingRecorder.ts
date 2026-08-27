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

  // New start/end time boundary states
  const [startTime, setStartTime] = useState<number>(0);
  const [endTime, setEndTime] = useState<number>(0);
  const hasSubmittedRef = useRef<boolean>(false);

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
      if (approvedSegments.length === 0) return;

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

      const currentMs = Math.round(startTime * 1000);
      const firstSegment = approvedSegments[0];
      const initialTimestamps = { [firstSegment.order]: currentMs };

      setTimestamps(initialTimestamps);
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

  // Recording operations
  const recordTimestamp = () => {
    if (!audioRef.current || recordingState !== 'Recording') return;
    
    const N = approvedSegments.length;
    if (N === 0) return;

    const currentMs = Math.round(audioRef.current.currentTime * 1000);

    if (activeIndex < N - 1) {
      const nextSegment = approvedSegments[activeIndex + 1];
      const updatedTimestamps = {
        ...timestamps,
        [nextSegment.order]: currentMs,
      };
      
      setTimestamps(updatedTimestamps);
      setActiveIndex(activeIndex + 1);
    }
  };

  const undoMark = () => {
    if (recordingState !== 'Recording') return;

    const N = approvedSegments.length;
    if (N === 0) return;

    const currentActiveSeg = approvedSegments[activeIndex];
    const currentSegOrder = currentActiveSeg?.order;

    const hasCurrentTimestamp = currentSegOrder !== undefined && timestamps[currentSegOrder] !== undefined;

    let targetIndex: number;
    if (hasCurrentTimestamp) {
      targetIndex = activeIndex;
    } else if (activeIndex > 0) {
      targetIndex = activeIndex - 1;
    } else {
      return;
    }

    // Do not delete initial start timestamp of segment 0 if activeIndex is 0
    if (targetIndex === 0 && approvedSegments[0] && timestamps[approvedSegments[0].order] !== undefined) {
      if (activeIndex > 0) {
        setActiveIndex(0);
        const seekTime = startTime;
        if (audioRef.current) {
          audioRef.current.currentTime = seekTime;
          setCurrentTime(seekTime);
        }
      }
      return;
    }

    const segToDelete = approvedSegments[targetIndex];
    if (segToDelete) {
      setTimestamps((prev) => {
        const updated = { ...prev };
        delete updated[segToDelete.order];
        return updated;
      });
    }

    const newIndex = Math.max(0, targetIndex - 1);
    setActiveIndex(newIndex);

    const prevSegOrder = approvedSegments[newIndex]?.order;
    const prevTimeMs = prevSegOrder !== undefined ? timestamps[prevSegOrder] : undefined;
    const targetSec = prevTimeMs !== undefined ? prevTimeMs / 1000 : startTime;
    const seekTime = Math.max(startTime, targetSec > startTime ? targetSec - 0.3 : startTime);

    if (audioRef.current) {
      audioRef.current.currentTime = seekTime;
      setCurrentTime(seekTime);
    }
  };

  const stepBack = undoMark;

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
      end_time_ms: Math.round(endTime * 1000),
      segments: segmentsPayload,
    };

    try {
      await onRecordingComplete(JSON.stringify(payload, null, 2));
      setRecordingState('Completed');
      setTimeout(() => {
        onCancel();
      }, 1500);
    } catch (err: any) {
      setErrorMsg(err.message || 'Auto-import timings failed.');
      setRecordingState('Error');
    }
  };

  const resetRecording = () => {
    const firstSegment = approvedSegments[0];
    const initialTimestamps = firstSegment ? { [firstSegment.order]: Math.round(startTime * 1000) } : {};
    setTimestamps(initialTimestamps);
    setActiveIndex(0);
    setErrorMsg(null);
    hasSubmittedRef.current = false;
    if (audioRef.current) {
      audioRef.current.currentTime = startTime;
      setCurrentTime(startTime);
    }
  };

  const resetAll = resetRecording;

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
