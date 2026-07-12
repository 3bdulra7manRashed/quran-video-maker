'use client';

import React, { useEffect, useRef } from 'react';
import { useApi } from '@/context/ApiContext';
import { useSegmentTimingRecorder, ApprovedSegment } from '@/hooks/useSegmentTimingRecorder';

interface SegmentTimingRecorderProps {
  surahNumber: number;
  reciterSlug: string;
  scope: 'full' | 'single' | 'range';
  ayahNumber: number | null;
  fromAyah: number | null;
  toAyah: number | null;
  approvedSegments: ApprovedSegment[];
  onRecordingComplete: (json: string) => Promise<void>;
  onCancel: () => void;
}

export default function SegmentTimingRecorder({
  surahNumber,
  reciterSlug,
  scope,
  ayahNumber,
  fromAyah,
  toAyah,
  approvedSegments,
  onRecordingComplete,
  onCancel,
}: SegmentTimingRecorderProps) {
  const { apiUrl } = useApi();
  const containerRef = useRef<HTMLDivElement | null>(null);

  const {
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
    audioEvents,
  } = useSegmentTimingRecorder({
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
  });

  // Focus container for keyboard event capturing
  useEffect(() => {
    containerRef.current?.focus();
  }, []);

  // Keyboard navigation and recording controls
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Prevent browser default actions (like page scrolling with space bar)
      if (['Space', ' ', 'Backspace', 'ArrowLeft', 'ArrowRight', 'Escape'].includes(e.key) || e.code === 'Space') {
        e.preventDefault();
      }

      if (recordingState === 'Importing' || recordingState === 'Completed') {
        return;
      }

      if (recordingState === 'Recording') {
        if (e.key === ' ' || e.code === 'Space') {
          recordTimestamp();
        } else if (e.key === 'Backspace') {
          stepBack();
        } else if (e.key === 'ArrowLeft') {
          navigatePrev();
        } else if (e.key === 'ArrowRight') {
          navigateNext();
        } else if (e.key === 'Escape') {
          onCancel();
        }
      } else if (recordingState === 'Ready') {
        if (e.key === ' ' || e.code === 'Space') {
          startRecording();
        } else if (e.key === 'Escape') {
          onCancel();
        }
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [recordingState, recordTimestamp, stepBack, navigateNext, navigatePrev, startRecording, onCancel]);

  const formatTime = (seconds: number) => {
    if (isNaN(seconds) || seconds === Infinity) return '0:00.000';
    const min = Math.floor(seconds / 60);
    const sec = Math.floor(seconds % 60);
    const ms = Math.floor((seconds % 1) * 1000);
    return `${min}:${sec.toString().padStart(2, '0')}.${ms.toString().padStart(3, '0')}`;
  };

  const activeSegment = approvedSegments[activeIndex];

  const renderStateBadge = () => {
    switch (recordingState) {
      case 'Loading':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 border border-amber-500/30 text-amber-400 animate-pulse font-mono">
            Loading Audio...
          </span>
        );
      case 'Ready':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-blue-500/10 border border-blue-500/30 text-blue-400 font-mono animate-pulse">
            Choose Start Time
          </span>
        );
      case 'Recording':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 animate-pulse font-mono">
            🔴 Recording...
          </span>
        );
      case 'Importing':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-mono animate-pulse">
            Importing...
          </span>
        );
      case 'Completed':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-emerald-600 text-white font-mono">
            ✓ Done
          </span>
        );
      case 'Error':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-red-500/10 border border-red-500/30 text-red-400 font-mono">
            Error
          </span>
        );
      case 'Idle':
      default:
        return null;
    }
  };

  return (
    <div
      ref={containerRef}
      tabIndex={0}
      className="fixed inset-0 z-50 bg-slate-950 text-slate-100 flex flex-col p-6 md:p-10 select-none font-sans outline-none overflow-y-auto"
    >
      {/* Hidden Audio Player */}
      <audio
        ref={audioRef}
        src={audioUrl}
        {...audioEvents}
      />

      {/* Header */}
      <div className="flex items-center justify-between border-b border-slate-800 pb-4 shrink-0">
        <div className="flex flex-col">
          <span className="text-xs text-slate-400 font-semibold uppercase tracking-wider">In-App recorder</span>
          <h3 className="text-lg font-bold text-white flex items-center gap-2 mt-0.5">
            <span>⏱️</span>
            <span>Segment Timing Recorder</span>
          </h3>
        </div>
        <div className="flex items-center gap-3">
          {renderStateBadge()}
          <button
            type="button"
            onClick={onCancel}
            className="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-850 hover:bg-slate-800 text-slate-300 transition cursor-pointer"
          >
            Cancel (Esc)
          </button>
        </div>
      </div>

      {/* Loading overlay */}
      {(recordingState === 'Idle' || recordingState === 'Loading') && (
        <div className="flex-1 flex flex-col items-center justify-center gap-4 py-12">
          <div className="w-10 h-10 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
          <span className="text-sm text-slate-400 font-mono">Streaming audio file from API server...</span>
        </div>
      )}

      {/* Ready state: Choose starting position screen */}
      {recordingState === 'Ready' && (
        <div className="flex-1 flex flex-col items-center justify-center max-w-4xl mx-auto w-full gap-8 py-8">
          <div className="text-center flex flex-col gap-2 max-w-xl">
            <h2 className="text-2xl font-bold text-white">Choose Recording Scope</h2>
            <p className="text-sm text-slate-400">
              Set the Start Position (required) and End Position (optional) to define the boundaries of your timing session.
            </p>
          </div>

          {/* Simple Slider & Play Preview */}
          <div className="w-full bg-slate-900 border border-slate-850 p-6 rounded-2xl flex flex-col gap-6 shadow-xl">
            {/* Range markers display */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="bg-slate-950/60 border border-slate-850 p-4 rounded-xl flex flex-col gap-1 text-center relative">
                <span className="text-[10px] uppercase font-bold text-emerald-400 tracking-wider">Start Position</span>
                <span className="text-lg font-bold font-mono text-white mt-1">{formatTime(startTime)}</span>
                <button
                  type="button"
                  onClick={() => seekTo(startTime)}
                  className="absolute top-3 left-3 text-xs opacity-60 hover:opacity-100 transition cursor-pointer"
                  title="Jump to Start"
                >
                  ⏮️
                </button>
                <button
                  type="button"
                  onClick={() => setStartTime(currentTime)}
                  className="mt-2 py-1.5 px-3 rounded-lg bg-emerald-950/40 hover:bg-emerald-900/40 border border-emerald-900/30 text-emerald-400 text-[10px] font-bold transition cursor-pointer"
                >
                  📍 Set to Playhead
                </button>
              </div>

              <div className="bg-slate-950/60 border border-slate-850 p-4 rounded-xl flex flex-col gap-1 text-center relative">
                <span className="text-[10px] uppercase font-bold text-red-400 tracking-wider">End Position (Optional)</span>
                <span className="text-lg font-bold font-mono text-white mt-1">{formatTime(endTime)}</span>
                <button
                  type="button"
                  onClick={() => seekTo(endTime)}
                  className="absolute top-3 left-3 text-xs opacity-60 hover:opacity-100 transition cursor-pointer"
                  title="Jump to End"
                >
                  ⏭️
                </button>
                <button
                  type="button"
                  onClick={() => setEndTime(currentTime)}
                  className="mt-2 py-1.5 px-3 rounded-lg bg-red-950/40 hover:bg-red-900/40 border border-red-900/30 text-red-400 text-[10px] font-bold transition cursor-pointer"
                >
                  🏁 Set to Playhead
                </button>
              </div>
            </div>

            {/* Live Recording Scope Summary */}
            <div className="bg-slate-950/40 border border-slate-850 p-4 rounded-xl flex flex-col gap-2.5">
              <span className="text-[10px] uppercase font-bold text-slate-500 tracking-wider text-center">Recording Scope Summary</span>
              <div className="grid grid-cols-3 gap-2 text-center text-xs font-mono text-slate-300">
                <div className="flex flex-col gap-0.5">
                  <span className="text-[9px] uppercase font-bold text-slate-500">Start</span>
                  <span className="text-emerald-400 font-bold">{formatTime(startTime)}</span>
                </div>
                <div className="flex flex-col gap-0.5 border-x border-slate-850">
                  <span className="text-[9px] uppercase font-bold text-slate-500">End</span>
                  <span className="text-red-400 font-bold">{formatTime(endTime)}</span>
                </div>
                <div className="flex flex-col gap-0.5">
                  <span className="text-[9px] uppercase font-bold text-slate-500">Duration</span>
                  <span className="text-blue-400 font-bold">{formatTime(Math.max(0, endTime - startTime))}</span>
                </div>
              </div>
            </div>

            <div className="flex items-center gap-4">
              <button
                type="button"
                onClick={togglePlayPause}
                className="w-14 h-14 flex items-center justify-center rounded-full bg-blue-600 hover:bg-blue-500 text-white text-xl font-bold transition shrink-0 cursor-pointer shadow-lg shadow-blue-950/40"
              >
                {isPlaying ? '⏸️' : '▶️'}
              </button>

              <div className="flex-1 flex flex-col gap-1">
                <input
                  type="range"
                  min={0}
                  max={duration || 100}
                  value={currentTime}
                  onChange={(e) => seekTo(Number(e.target.value))}
                  className="w-full h-2 bg-slate-800 rounded-full appearance-none cursor-pointer accent-blue-500"
                />
                <div className="flex items-center justify-between text-xs font-mono text-slate-500 mt-1">
                  <span>{formatTime(currentTime)}</span>
                  <span>{formatTime(duration)}</span>
                </div>
              </div>
            </div>

            {/* Seeking adjustment grid */}
            <div className="flex flex-col gap-2.5">
              <span className="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Fine-tune seek position</span>
              <div className="grid grid-cols-3 md:grid-cols-6 gap-2">
                {[
                  { label: '-5m', val: -300 },
                  { label: '-1m', val: -60 },
                  { label: '-30s', val: -30 },
                  { label: '-15s', val: -15 },
                  { label: '-5s', val: -5 },
                  { label: '-1s', val: -1 },
                  { label: '+1s', val: 1 },
                  { label: '+5s', val: 5 },
                  { label: '+15s', val: 15 },
                  { label: '+30s', val: 30 },
                  { label: '+1m', val: 60 },
                  { label: '+5m', val: 300 },
                ].map((btn) => (
                  <button
                    key={btn.label}
                    type="button"
                    onClick={() => seek(btn.val)}
                    className="py-2.5 px-2 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-200 text-xs font-bold font-mono transition cursor-pointer"
                  >
                    {btn.label}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {errorMsg && (
            <div className="bg-red-950/40 border border-red-900/50 p-4 rounded-xl text-xs text-red-400 font-sans max-w-sm w-full text-center">
              {errorMsg}
            </div>
          )}

          <button
            type="button"
            onClick={startRecording}
            className="w-full max-w-sm py-4 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-sm transition shadow-lg shadow-emerald-950/40 tracking-wider cursor-pointer"
          >
            ⚡ Start Recording (Space)
          </button>
        </div>
      )}

      {/* Recording and other operational states */}
      {['Recording', 'Importing', 'Completed', 'Error'].includes(recordingState) && (
        <div className="flex-1 flex flex-col gap-6 py-6 overflow-hidden">
          
          {/* Main workspace layout */}
          <div className="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-6 min-h-0">
            
            {/* Active focus segment (LHS) */}
            <div className="lg:col-span-8 bg-slate-950/30 border border-slate-850 rounded-2xl p-6 flex flex-col items-center justify-center text-center relative gap-6">
              {recordingState === 'Importing' && (
                <div className="absolute inset-0 bg-slate-950/80 rounded-2xl z-20 flex flex-col items-center justify-center gap-3">
                  <div className="w-8 h-8 border-3 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                  <span className="text-xs text-indigo-400 font-mono font-semibold">Auto-importing timing alignments...</span>
                </div>
              )}

              {recordingState === 'Completed' && (
                <div className="absolute inset-0 bg-slate-950/85 rounded-2xl z-20 flex flex-col items-center justify-center gap-3 animate-fade-in">
                  <span className="text-3xl">✅</span>
                  <span className="text-sm text-emerald-400 font-bold tracking-wide">
                    Segment timings imported successfully.
                  </span>
                </div>
              )}

              {activeSegment ? (
                <>
                  <span className="px-3.5 py-1 rounded-full text-xs font-bold bg-emerald-950/60 border border-emerald-900/50 text-emerald-400 font-mono shrink-0">
                    Segment {activeIndex + 1} of {approvedSegments.length}
                  </span>

                  <div className="flex-1 flex flex-col items-center justify-center gap-4 max-w-3xl">
                    {/* Arabic Verse Segment */}
                    <h2 className="text-3xl md:text-4xl font-semibold text-emerald-400 leading-loose py-4 font-sans select-text" dir="rtl">
                      {activeSegment.arabic}
                    </h2>

                    {/* English Translation */}
                    <p className="text-base md:text-lg text-slate-200 italic max-w-2xl leading-relaxed select-text">
                      {activeSegment.translation}
                    </p>

                    {/* Tafsir reference */}
                    {activeSegment.tafsir && (
                      <p className="text-xs text-slate-500 max-w-xl leading-relaxed mt-4 border-t border-slate-900 pt-3 select-text">
                        {activeSegment.tafsir}
                      </p>
                    )}
                  </div>

                  <div className="shrink-0 flex flex-col items-center gap-1.5 mt-2 bg-slate-900/50 px-4 py-2 rounded-xl border border-slate-900">
                    <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Recorded Start Time</span>
                    <span className="text-xl font-bold font-mono text-white">
                      {timestamps[activeSegment.order] !== undefined
                        ? formatTime(timestamps[activeSegment.order] / 1000)
                        : 'Pending Tap...'}
                    </span>
                  </div>
                </>
              ) : (
                <div className="text-slate-500 text-sm">No segments loaded.</div>
              )}
            </div>

            {/* Segments timeline list (RHS) */}
            <div className="lg:col-span-4 bg-slate-950/20 border border-slate-850 rounded-2xl p-4 flex flex-col gap-3 min-h-0 overflow-y-auto">
              <span className="text-xs font-bold text-slate-450 uppercase tracking-wider px-1">Segment timeline status</span>
              <div className="flex-1 flex flex-col gap-2 overflow-y-auto pr-1">
                {approvedSegments.map((seg, i) => {
                  const hasTime = timestamps[seg.order] !== undefined;
                  const isActive = i === activeIndex;
                  return (
                    <div
                      key={seg.order}
                      onClick={() => {
                        if (recordingState === 'Recording') {
                          setActiveIndex(i);
                        }
                      }}
                      className={`p-3 rounded-xl border flex items-center justify-between transition cursor-pointer ${
                        isActive
                          ? 'bg-slate-900 border-emerald-500 shadow-md shadow-emerald-950/20'
                          : hasTime
                          ? 'bg-slate-900/60 border-slate-800 text-slate-350'
                          : 'bg-slate-950/40 border-slate-900/80 text-slate-500'
                      }`}
                    >
                      <div className="flex items-center gap-2.5 min-w-0">
                        <span className={`w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold font-mono shrink-0 ${
                          isActive
                            ? 'bg-emerald-500 text-slate-950'
                            : hasTime
                            ? 'bg-emerald-950 text-emerald-400'
                            : 'bg-slate-800 text-slate-500'
                        }`}>
                          {hasTime ? '✓' : i + 1}
                        </span>
                        <span className="text-xs font-mono font-semibold truncate shrink-0 max-w-[100px] text-right" dir="rtl">
                          {seg.arabic}
                        </span>
                      </div>
                      <span className="text-xs font-mono font-bold">
                        {hasTime ? formatTime(timestamps[seg.order] / 1000) : '--:--.---'}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>

          {/* Bottom controls panel */}
          <div className="flex flex-col gap-4 shrink-0 border-t border-slate-900 pt-4">
            
            {/* Audio slider and timer indicators */}
            <div className="flex items-center gap-4 bg-slate-950/40 border border-slate-900 p-4 rounded-2xl shadow-inner">
              <button
                type="button"
                disabled={recordingState === 'Importing' || recordingState === 'Completed'}
                onClick={togglePlayPause}
                className="w-12 h-12 flex items-center justify-center rounded-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold transition shadow-lg shadow-emerald-950/40 shrink-0 cursor-pointer disabled:opacity-40"
              >
                {isPlaying ? '⏸️' : '▶️'}
              </button>

              <div className="flex-1 flex flex-col gap-1">
                <div className="w-full bg-slate-800 h-2 rounded-full overflow-hidden relative">
                  <div
                    className="bg-emerald-500 h-full transition-all duration-100 ease-linear"
                    style={{ width: `${duration > 0 ? (currentTime / duration) * 100 : 0}%` }}
                  ></div>
                </div>
                <div className="flex items-center justify-between text-xs font-mono text-slate-500">
                  <span>{formatTime(currentTime)}</span>
                  <span>{formatTime(duration)}</span>
                </div>
              </div>
            </div>

            {/* Error notifications and manual recovery */}
            {recordingState === 'Error' && errorMsg && (
              <div className="bg-red-950/20 text-red-400 border border-red-900/40 p-4 rounded-xl text-xs flex flex-col gap-3 font-sans">
                <div className="font-semibold">{errorMsg}</div>
                {Object.keys(timestamps).length > 0 && (
                  <div className="flex items-center gap-3">
                    <button
                      type="button"
                      onClick={() => submitTimings()}
                      className="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-1.5 px-3 rounded-lg text-[10px] cursor-pointer"
                    >
                      🔄 Retry Auto-Import
                    </button>
                    <button
                      type="button"
                      onClick={resetRecording}
                      className="bg-slate-800 hover:bg-slate-750 text-slate-350 font-semibold py-1.5 px-3 rounded-lg text-[10px] cursor-pointer"
                    >
                      🗑️ Reset & Start Over
                    </button>
                  </div>
                )}
              </div>
            )}

            {/* Quick instructions bar */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2 bg-slate-950/30 border border-slate-900 p-3 rounded-xl text-[10px] text-slate-400 font-sans leading-relaxed">
              <div className="flex items-center gap-1.5">
                <kbd className="px-1.5 py-0.5 rounded bg-slate-800 text-white font-mono font-bold">Space</kbd>
                <span>Record Timestamp & Advance</span>
              </div>
              <div className="flex items-center gap-1.5">
                <kbd className="px-1.5 py-0.5 rounded bg-slate-800 text-white font-mono font-bold">Backspace</kbd>
                <span>Undo Timestamp & Step Back</span>
              </div>
              <div className="flex items-center gap-1.5">
                <kbd className="px-1.5 py-0.5 rounded bg-slate-800 text-white font-mono font-bold">← / →</kbd>
                <span>Navigate Select Only</span>
              </div>
              <div className="flex items-center gap-1.5">
                <kbd className="px-1.5 py-0.5 rounded bg-slate-800 text-white font-mono font-bold">Esc</kbd>
                <span>Cancel Recording</span>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
