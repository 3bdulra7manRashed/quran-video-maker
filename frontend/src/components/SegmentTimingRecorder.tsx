'use client';

import React, { useEffect, useRef } from 'react';
import { useApi } from '@/context/ApiContext';
import { useSegmentTimingRecorder, ApprovedSegment } from '@/hooks/useSegmentTimingRecorder';
import { useLanguage } from '@/hooks/useLanguage';

interface SegmentTimingRecorderProps {
  surahNumber: number;
  reciterSlug: string;
  scope: 'full' | 'single' | 'range';
  ayahNumber: number | null;
  fromAyah: number | null;
  toAyah: number | null;
  approvedSegments: ApprovedSegment[];
  persistToDatabase?: boolean;
  onPersistToDatabaseChange?: (val: boolean) => void;
  onRecordingComplete: (json: string, persistToDatabase?: boolean) => Promise<void>;
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
  persistToDatabase = true,
  onPersistToDatabaseChange,
  onRecordingComplete,
  onCancel,
}: SegmentTimingRecorderProps) {
  const { apiUrl } = useApi();
  const { language } = useLanguage();
  const isAr = language === 'ar';
  const containerRef = useRef<HTMLDivElement | null>(null);

  const {
    audioRef,
    audioUrl,
    recordingState,
    activeIndex,
    setActiveIndex,
    timestamps,
    segmentTimings,
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
    undoMark,
    stepBack,
    navigateNext,
    navigatePrev,
    submitTimings,
    resetRecording,
    resetAll,
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
    persistToDatabase,
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
        } else if (e.key === 'Backspace' || (e.ctrlKey && e.key.toLowerCase() === 'z')) {
          undoMark();
        } else if (e.key.toLowerCase() === 'r' && (e.shiftKey || e.ctrlKey)) {
          resetRecording();
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
  }, [recordingState, recordTimestamp, undoMark, stepBack, resetRecording, navigateNext, navigatePrev, startRecording, onCancel]);

  const formatTime = (seconds: number) => {
    if (isNaN(seconds) || seconds === Infinity || seconds < 0) return '00:00.000';
    const hrs = Math.floor(seconds / 3600);
    const min = Math.floor((seconds % 3600) / 60);
    const sec = Math.floor(seconds % 60);
    const ms = Math.floor((seconds % 1) * 1000);
    if (hrs > 0) {
      return `${hrs}:${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}.${ms.toString().padStart(3, '0')}`;
    }
    return `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}.${ms.toString().padStart(3, '0')}`;
  };

  const activeSegment = approvedSegments[activeIndex];

  const renderStateBadge = () => {
    switch (recordingState) {
      case 'Loading':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-[#f2ca50]/15 border border-[#f2ca50]/30 text-[#f2ca50] animate-pulse font-mono flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-full bg-[#f2ca50] animate-ping"></span>
            <span>{isAr ? 'جاري تحميل الصوت...' : 'Loading Audio...'}</span>
          </span>
        );
      case 'Ready':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-[#45dfa4]/15 border border-[#45dfa4]/30 text-[#45dfa4] font-mono flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-full bg-[#45dfa4]"></span>
            <span>{isAr ? 'جاهز لتحديد النطاق' : 'Ready to Scope'}</span>
          </span>
        );
      case 'Recording':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-rose-500/15 border border-rose-500/30 text-rose-400 animate-pulse font-mono flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
            <span>{isAr ? '🔴 تسجيل مباشر نشط' : '🔴 Live Recording'}</span>
          </span>
        );
      case 'Importing':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/15 border border-indigo-500/30 text-indigo-400 font-mono animate-pulse flex items-center gap-1.5">
            <span className="material-symbols-outlined text-[14px] animate-spin">sync</span>
            <span>{isAr ? 'جاري الاستيراد...' : 'Importing...'}</span>
          </span>
        );
      case 'Completed':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-[#45dfa4]/20 border border-[#45dfa4]/40 text-[#45dfa4] font-mono flex items-center gap-1.5">
            <span className="material-symbols-outlined text-[14px]">check_circle</span>
            <span>{isAr ? 'مكتمل بنجاح' : 'Done'}</span>
          </span>
        );
      case 'Error':
        return (
          <span className="px-3 py-1 rounded-full text-xs font-bold bg-red-500/15 border border-red-500/30 text-red-400 font-mono flex items-center gap-1.5">
            <span className="material-symbols-outlined text-[14px]">error</span>
            <span>{isAr ? 'حدث خطأ' : 'Error'}</span>
          </span>
        );
      case 'Idle':
      default:
        return null;
    }
  };

  // Safe calculation of start/end percentages for timeline preview
  const startPercent = duration > 0 ? Math.min(100, Math.max(0, (startTime / duration) * 100)) : 0;
  const endPercent = duration > 0 && endTime > 0 ? Math.min(100, Math.max(0, (endTime / duration) * 100)) : 100;
  const currentPercent = duration > 0 ? Math.min(100, Math.max(0, (currentTime / duration) * 100)) : 0;

  return (
    <div
      ref={containerRef}
      tabIndex={0}
      className="fixed inset-0 z-50 bg-[#0e1513] text-[#dde4e0] flex flex-col p-4 sm:p-8 select-none font-sans outline-none overflow-y-auto"
    >
      {/* Hidden Audio Player */}
      <audio
        ref={audioRef}
        src={audioUrl}
        {...audioEvents}
      />

      {/* Studio Header */}
      <div className="flex items-center justify-between border-b border-[#26332e] pb-4 shrink-0 max-w-6xl mx-auto w-full">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#1a211f] to-[#131a18] border border-[#f2ca50]/30 flex items-center justify-center shadow-[0_0_15px_rgba(242,202,80,0.15)] text-[#f2ca50]">
            <span className="material-symbols-outlined text-[24px]">timer</span>
          </div>
          <div className="flex flex-col">
            <div className="flex items-center gap-2">
              <h3 className="text-base sm:text-lg font-bold text-white tracking-tight">
                {isAr ? 'مسجل توقيت المقاطع التفاعلي' : 'Segment Timing Recorder'}
              </h3>
              <span className="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-[#f2ca50]/15 text-[#f2ca50] border border-[#f2ca50]/30">
                v2.4 AI
              </span>
            </div>
            <span className="text-[11px] text-[#94a3b8]">
              {isAr ? 'مزامنة دقيقة وسريعة لأجزاء الآيات القرآنية مع التلاوة الصوتية' : 'Interactive In-App Verse Timing Alignment & Tapping Studio'}
            </span>
          </div>
        </div>

        <div className="flex items-center gap-3">
          {renderStateBadge()}
          <button
            type="button"
            tabIndex={-1}
            onClick={onCancel}
            className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-[#1a211f] hover:bg-[#242b29] text-[#dde4e0] border border-[#26332e] transition-colors cursor-pointer"
          >
            <span className="material-symbols-outlined text-[16px]">close</span>
            <span>{isAr ? 'إلغاء (Esc)' : 'Cancel (Esc)'}</span>
          </button>
        </div>
      </div>

      {/* Loading overlay */}
      {(recordingState === 'Idle' || recordingState === 'Loading') && (
        <div className="flex-1 flex flex-col items-center justify-center gap-4 py-16">
          <div className="w-12 h-12 border-3 border-[#f2ca50] border-t-transparent rounded-full animate-spin"></div>
          <div className="flex flex-col items-center gap-1 text-center">
            <span className="text-sm font-semibold text-white">
              {isAr ? 'جاري تحميل وتجهيز الملف الصوتي من الخادم...' : 'Streaming audio file from API server...'}
            </span>
            <span className="text-xs text-[#94a3b8] font-mono">
              Surah {surahNumber} • {reciterSlug}
            </span>
          </div>
        </div>
      )}

      {/* Ready state: Choose starting & ending position screen */}
      {recordingState === 'Ready' && (
        <div className="flex-1 flex flex-col items-center justify-center max-w-4xl mx-auto w-full gap-6 py-6 animate-fade-in">
          <div className="text-center flex flex-col gap-1.5 max-w-2xl">
            <h2 className="text-xl sm:text-2xl font-extrabold text-white tracking-tight">
              {isAr ? 'تحديد نطاق التسجيل الصوتي' : 'Choose Recording Scope'}
            </h2>
            <p className="text-xs sm:text-sm text-[#94a3b8] leading-relaxed">
              {isAr
                ? 'حدد نقطة البداية (إلزامية) ونقطة النهاية (اختيارية) لتسجيل توقيت مقاطع الآيات بدقة متناهية.'
                : 'Set the Start Position (required) and End Position (optional) to define the exact boundaries of your timing session.'}
            </p>
          </div>

          {/* Main Scope Control Card */}
          <div className="w-full bg-[#131a18] border border-[#26332e] p-5 sm:p-7 rounded-2xl flex flex-col gap-6 shadow-[0_4px_25px_rgba(0,0,0,0.5)]">
            
            {/* Start & End Dual Time Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {/* Start Position Card */}
              <div className="bg-[#0e1513] border-2 border-[#45dfa4]/40 hover:border-[#45dfa4]/70 p-4 sm:p-5 rounded-2xl flex flex-col gap-2 relative transition-all shadow-[0_0_20px_rgba(69,223,164,0.06)]">
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-bold text-[#45dfa4] tracking-wider uppercase flex items-center gap-1.5">
                    <span className="material-symbols-outlined text-[16px]">play_circle</span>
                    <span>{isAr ? 'نقطة البداية (إلزامية)' : 'Start Position (Required)'}</span>
                  </span>
                  <button
                    type="button"
                    onClick={() => seekTo(startTime)}
                    className="p-1.5 rounded-lg bg-[#1a211f] hover:bg-[#242b29] text-[#45dfa4] text-xs transition cursor-pointer flex items-center gap-1"
                    title={isAr ? 'القفز لموضع البداية' : 'Jump to Start'}
                  >
                    <span className="material-symbols-outlined text-[16px]">skip_previous</span>
                  </button>
                </div>

                <div className="py-2 text-center">
                  <span className="text-2xl sm:text-3xl font-extrabold font-mono text-white tracking-wider" dir="ltr">
                    {formatTime(startTime)}
                  </span>
                </div>

                <button
                  type="button"
                  onClick={() => setStartTime(currentTime)}
                  className="mt-1 py-2 px-3 rounded-xl bg-[#45dfa4]/15 hover:bg-[#45dfa4]/25 border border-[#45dfa4]/40 text-[#45dfa4] text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shadow-sm"
                >
                  <span className="material-symbols-outlined text-[16px]">pin_drop</span>
                  <span>{isAr ? 'ضبط على الموضع الحالي' : 'Set to Playhead'}</span>
                </button>
              </div>

              {/* End Position Card */}
              <div className="bg-[#0e1513] border-2 border-[#f2ca50]/40 hover:border-[#f2ca50]/70 p-4 sm:p-5 rounded-2xl flex flex-col gap-2 relative transition-all shadow-[0_0_20px_rgba(242,202,80,0.06)]">
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-bold text-[#f2ca50] tracking-wider uppercase flex items-center gap-1.5">
                    <span className="material-symbols-outlined text-[16px]">stop_circle</span>
                    <span>{isAr ? 'نقطة النهاية (اختيارية)' : 'End Position (Optional)'}</span>
                  </span>
                  <button
                    type="button"
                    onClick={() => seekTo(endTime)}
                    className="p-1.5 rounded-lg bg-[#1a211f] hover:bg-[#242b29] text-[#f2ca50] text-xs transition cursor-pointer flex items-center gap-1"
                    title={isAr ? 'القفز لموضع النهاية' : 'Jump to End'}
                  >
                    <span className="material-symbols-outlined text-[16px]">skip_next</span>
                  </button>
                </div>

                <div className="py-2 text-center">
                  <span className="text-2xl sm:text-3xl font-extrabold font-mono text-white tracking-wider" dir="ltr">
                    {formatTime(endTime)}
                  </span>
                </div>

                <button
                  type="button"
                  onClick={() => setEndTime(currentTime)}
                  className="mt-1 py-2 px-3 rounded-xl bg-[#f2ca50]/15 hover:bg-[#f2ca50]/25 border border-[#f2ca50]/40 text-[#f2ca50] text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shadow-sm"
                >
                  <span className="material-symbols-outlined text-[16px]">flag</span>
                  <span>{isAr ? 'ضبط على الموضع الحالي' : 'Set to Playhead'}</span>
                </button>
              </div>
            </div>

            {/* Scope Summary & Visual Range Mini-Bar */}
            <div className="bg-[#0e1513] border border-[#26332e] p-4 rounded-xl flex flex-col gap-3">
              <div className="flex items-center justify-between text-xs border-b border-[#26332e]/60 pb-2">
                <span className="text-[10px] uppercase font-bold text-[#94a3b8] tracking-wider flex items-center gap-1.5">
                  <span className="material-symbols-outlined text-[14px] text-[#f2ca50]">analytics</span>
                  <span>{isAr ? 'ملخص نطاق الجلسة الصوتي' : 'Recording Scope Summary'}</span>
                </span>
                <span className="text-[10px] text-[#94a3b8] font-mono">
                  {approvedSegments.length} {isAr ? 'مقطع معتمد' : 'segments'}
                </span>
              </div>

              {/* 3 Metric Columns */}
              <div className="grid grid-cols-3 gap-2 text-center text-xs font-mono">
                <div className="flex flex-col gap-0.5">
                  <span className="text-[10px] font-semibold text-[#94a3b8]">{isAr ? 'البداية' : 'Start'}</span>
                  <span className="text-[#45dfa4] font-bold text-sm" dir="ltr">{formatTime(startTime)}</span>
                </div>
                <div className="flex flex-col gap-0.5 border-x border-[#26332e]">
                  <span className="text-[10px] font-semibold text-[#94a3b8]">{isAr ? 'النهاية' : 'End'}</span>
                  <span className="text-[#f2ca50] font-bold text-sm" dir="ltr">{formatTime(endTime)}</span>
                </div>
                <div className="flex flex-col gap-0.5">
                  <span className="text-[10px] font-semibold text-[#94a3b8]">{isAr ? 'المدة التقديرية' : 'Duration'}</span>
                  <span className="text-sky-400 font-bold text-sm" dir="ltr">{formatTime(Math.max(0, endTime - startTime))}</span>
                </div>
              </div>

              {/* Visual Track Representation Bar */}
              <div className="relative w-full h-3 bg-[#1a211f] rounded-full overflow-hidden border border-[#26332e] mt-1" dir="ltr">
                {/* Active scope highlight */}
                <div
                  className="absolute top-0 bottom-0 bg-gradient-to-r from-[#45dfa4] via-[#f2ca50] to-[#f2ca50] opacity-40"
                  style={{
                    left: `${startPercent}%`,
                    width: `${Math.max(2, endPercent - startPercent)}%`,
                  }}
                />
                {/* Current Playhead Cursor */}
                <div
                  className="absolute top-0 bottom-0 w-1 bg-white shadow-[0_0_8px_#ffffff] z-10"
                  style={{ left: `${currentPercent}%` }}
                />
              </div>
            </div>

            {/* Audio Scrubber & Main Play Button */}
            <div className="flex items-center gap-4 bg-[#0e1513] border border-[#26332e] p-4 rounded-2xl">
              <button
                type="button"
                onClick={togglePlayPause}
                className="w-13 h-13 flex items-center justify-center rounded-full bg-gradient-to-tr from-[#f2ca50] to-[#ffd768] hover:from-[#ffd768] hover:to-[#f2ca50] text-[#0e1513] font-bold transition-all shrink-0 cursor-pointer shadow-[0_0_20px_rgba(242,202,80,0.35)] hover:scale-105"
                title={isPlaying ? 'Pause' : 'Play'}
              >
                <span className="material-symbols-outlined text-[28px]">
                  {isPlaying ? 'pause' : 'play_arrow'}
                </span>
              </button>

              <div className="flex-1 flex flex-col gap-1.5">
                <div className="flex items-center justify-between text-xs font-mono text-[#94a3b8]">
                  <span className="text-white font-bold text-sm" dir="ltr">{formatTime(currentTime)}</span>
                  <span className="text-[#94a3b8]" dir="ltr">{formatTime(duration)}</span>
                </div>
                <input
                  type="range"
                  min={0}
                  max={duration || 100}
                  value={currentTime}
                  onChange={(e) => seekTo(Number(e.target.value))}
                  className="w-full h-2.5 bg-[#1a211f] rounded-full appearance-none cursor-pointer accent-[#f2ca50]"
                  dir="ltr"
                />
              </div>
            </div>

            {/* Fine-tune Seek Position Grid */}
            <div className="flex flex-col gap-2.5">
              <span className="text-[10px] uppercase font-bold text-[#94a3b8] tracking-wider flex items-center gap-1.5">
                <span className="material-symbols-outlined text-[14px] text-[#f2ca50]">tune</span>
                <span>{isAr ? 'التقديم والتأخير الدقيق في التلاوة' : 'Fine-Tune Seek Position'}</span>
              </span>

              {/* Rewind & Forward Rows - explicitly dir="ltr" to prevent text inversion in RTL */}
              <div className="grid grid-cols-6 gap-2" dir="ltr">
                {[
                  { label: '-5m', val: -300 },
                  { label: '-1m', val: -60 },
                  { label: '-30s', val: -30 },
                  { label: '-15s', val: -15 },
                  { label: '-5s', val: -5 },
                  { label: '-1s', val: -1 },
                ].map((btn) => (
                  <button
                    key={btn.label}
                    type="button"
                    onClick={() => seek(btn.val)}
                    className="py-2.5 px-1 rounded-xl bg-[#1a211f] hover:bg-[#242b29] hover:border-[#f2ca50]/50 border border-[#26332e] text-[#dde4e0] hover:text-[#f2ca50] text-xs font-bold font-mono transition-all cursor-pointer shadow-sm flex items-center justify-center gap-0.5"
                  >
                    <span>{btn.label}</span>
                  </button>
                ))}
              </div>

              <div className="grid grid-cols-6 gap-2" dir="ltr">
                {[
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
                    className="py-2.5 px-1 rounded-xl bg-[#1a211f] hover:bg-[#242b29] hover:border-[#45dfa4]/50 border border-[#26332e] text-[#dde4e0] hover:text-[#45dfa4] text-xs font-bold font-mono transition-all cursor-pointer shadow-sm flex items-center justify-center gap-0.5"
                  >
                    <span>{btn.label}</span>
                  </button>
                ))}
              </div>
            </div>
          </div>

          {errorMsg && (
            <div className="bg-red-500/10 border border-red-500/30 p-4 rounded-xl text-xs text-red-400 font-sans max-w-lg w-full text-center">
              {errorMsg}
            </div>
          )}

          {/* Persistence Mode Setting */}
          <div className="flex items-center justify-between gap-3 w-full max-w-lg px-5 py-3.5 rounded-xl bg-[#131a18] border border-[#26332e] shadow-sm">
            <div className="flex flex-col text-start">
              <label htmlFor="readyPersistToDb" className="text-xs text-white font-semibold cursor-pointer select-none">
                {isAr ? 'حفظ كقالب معتمد في قاعدة البيانات' : 'Save permanently to Database'}
              </label>
              <span className="text-[10px] text-[#94a3b8]">
                {persistToDatabase
                  ? (isAr ? 'سيتم تثبيت التوقيت كقالب دائم في قاعدة البيانات لجميع الاستخدامات اللاحقة' : 'Timings will be permanently saved to database for future renders')
                  : (isAr ? 'استخدام التوقيت للجلسة الحالية فقط (Run-only)' : 'Temporary run-only timing for this session')}
              </span>
            </div>
            <input
              id="readyPersistToDb"
              type="checkbox"
              tabIndex={-1}
              checked={persistToDatabase}
              onChange={(e) => onPersistToDatabaseChange?.(e.target.checked)}
              className="w-4 h-4 accent-[#f2ca50] rounded bg-[#0e1513] border-[#26332e] cursor-pointer shrink-0"
            />
          </div>

          {/* Giant Gold CTA Button to Start Recording */}
          <button
            type="button"
            tabIndex={-1}
            onClick={startRecording}
            className="w-full max-w-lg py-4 px-6 rounded-2xl bg-gradient-to-r from-[#f2ca50] to-[#ffd768] hover:from-[#ffd768] hover:to-[#f2ca50] text-[#0e1513] font-black text-sm uppercase tracking-wider transition-all shadow-[0_4px_30px_rgba(242,202,80,0.3)] hover:scale-[1.01] flex items-center justify-center gap-2 cursor-pointer"
          >
            <span className="material-symbols-outlined text-[22px]">bolt</span>
            <span>{isAr ? 'بدء تسجيل التوقيت (اضغط مسافة / Space)' : 'Start Recording (Space)'}</span>
          </button>
        </div>
      )}

      {/* Recording and other operational states */}
      {['Recording', 'Importing', 'Completed', 'Error'].includes(recordingState) && (
        <div className="flex-1 flex flex-col gap-4 py-4 overflow-hidden max-w-6xl mx-auto w-full">
          
          {/* Main workspace layout */}
          <div className="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-5 min-h-0">
            
            {/* Active focus segment (LHS) */}
            <div className="lg:col-span-8 bg-[#131a18] border border-[#26332e] rounded-2xl p-5 sm:p-6 flex flex-col items-center justify-between text-center relative gap-4 shadow-2xl overflow-y-auto">
              {recordingState === 'Importing' && (
                <div className="absolute inset-0 bg-[#0e1513]/90 rounded-2xl z-20 flex flex-col items-center justify-center gap-3 backdrop-blur-sm">
                  <div className="w-10 h-10 border-3 border-[#f2ca50] border-t-transparent rounded-full animate-spin"></div>
                  <span className="text-xs text-[#f2ca50] font-mono font-semibold">
                    {isAr ? 'جاري استيراد وحفظ التواقيت في قاعدة البيانات...' : 'Auto-importing timing alignments...'}
                  </span>
                </div>
              )}

              {recordingState === 'Completed' && (
                <div className="absolute inset-0 bg-[#0e1513]/95 rounded-2xl z-20 flex flex-col items-center justify-center gap-3 animate-fade-in backdrop-blur-sm">
                  <span className="material-symbols-outlined text-[48px] text-[#45dfa4]">check_circle</span>
                  <span className="text-base text-[#45dfa4] font-bold tracking-wide">
                    {isAr ? 'تم استيراد وحفظ توقيت المقاطع بنجاح!' : 'Segment timings imported successfully.'}
                  </span>
                </div>
              )}

              {activeSegment ? (
                <>
                  <div className="flex items-center gap-2">
                    <span className="px-3.5 py-1 rounded-full text-xs font-bold bg-[#f2ca50]/15 border border-[#f2ca50]/30 text-[#f2ca50] font-mono shrink-0">
                      {isAr ? `المقطع ${activeIndex + 1} من ${approvedSegments.length}` : `Segment ${activeIndex + 1} of ${approvedSegments.length}`}
                    </span>
                  </div>

                  <div className="flex-1 flex flex-col items-center justify-center gap-4 max-w-3xl">
                    {/* Arabic Verse Segment in Quranic Calligraphy Font */}
                    <h2 className="text-3xl md:text-4xl lg:text-5xl font-quran text-white leading-[2] py-2 select-text" dir="rtl">
                      {activeSegment.arabic}
                    </h2>

                    {/* English Translation */}
                    <p className="text-base md:text-lg text-[#94a3b8] italic max-w-2xl leading-relaxed select-text font-serif">
                      {activeSegment.translation}
                    </p>

                    {/* Tafsir reference */}
                    {activeSegment.tafsir && (
                      <p className="text-xs text-[#94a3b8]/70 max-w-xl leading-relaxed mt-2 border-t border-[#26332e] pt-3 select-text">
                        {activeSegment.tafsir}
                      </p>
                    )}
                  </div>

                  <div className="shrink-0 flex flex-col items-center gap-1 mt-2 bg-[#0e1513] px-6 py-3 rounded-2xl border border-[#26332e]">
                    <span className="text-[10px] font-bold text-[#94a3b8] uppercase tracking-wider">
                      {isAr ? 'توقيت بداية هذا المقطع' : 'Recorded Start Time'}
                    </span>
                    <span className="text-2xl font-bold font-mono text-[#45dfa4]" dir="ltr">
                      {segmentTimings[activeSegment.order]?.start_ms !== undefined
                        ? formatTime(segmentTimings[activeSegment.order].start_ms / 1000)
                        : (timestamps[activeSegment.order] !== undefined
                            ? formatTime(timestamps[activeSegment.order] / 1000)
                            : (activeIndex === 0 ? formatTime(startTime) : (isAr ? 'في انتظار الضغط...' : 'Pending Tap...')))}
                    </span>
                  </div>

                  {/* Persistence Mode Toggle Indicator */}
                  <div className="flex items-center justify-center gap-2 mt-1 px-3.5 py-1.5 rounded-xl bg-[#0e1513] border border-[#26332e]">
                    <input
                      id="recPersistToDb"
                      type="checkbox"
                      tabIndex={-1}
                      checked={persistToDatabase}
                      onChange={(e) => onPersistToDatabaseChange?.(e.target.checked)}
                      className="w-3.5 h-3.5 accent-[#f2ca50] rounded bg-[#0e1513] border-[#26332e] cursor-pointer"
                    />
                    <label htmlFor="recPersistToDb" className="text-[11px] text-[#94a3b8] font-medium cursor-pointer select-none">
                      {isAr ? 'حفظ كقالب معتمد في قاعدة البيانات' : 'Save permanently to Database'}
                    </label>
                  </div>
                </>
              ) : (
                <div className="text-[#94a3b8] text-sm">
                  {isAr ? 'لا توجد مقاطع معتمدة.' : 'No segments loaded.'}
                </div>
              )}
            </div>

            {/* Segments timeline list (RHS) */}
            <div className="lg:col-span-4 bg-[#131a18] border border-[#26332e] rounded-2xl p-4 flex flex-col gap-3 min-h-0 overflow-y-auto shadow-xl">
              <div className="flex items-center justify-between px-1 gap-2 border-b border-[#26332e] pb-2">
                <span className="text-xs font-bold text-white uppercase tracking-wider truncate flex items-center gap-1.5">
                  <span className="material-symbols-outlined text-[16px] text-[#f2ca50]">format_list_numbered</span>
                  <span>{isAr ? 'جدول المقاطع المعتمدة' : 'Segments Timeline'}</span>
                </span>
                <span className="text-[10px] font-mono text-[#94a3b8]">
                  {approvedSegments.length}
                </span>
              </div>

              <div className="flex-1 flex flex-col gap-2 overflow-y-auto pr-1">
                {approvedSegments.map((seg, i) => {
                  const timing = segmentTimings[seg.order];
                  const hasStart = timing?.start_ms !== undefined || (i === 0 && recordingState === 'Recording');
                  const isCompleted = timing?.end_ms !== undefined;
                  const isActive = i === activeIndex;
                  const uniqueKey = `seg-${seg.order ?? (seg as any).segment_order ?? i}-${i}`;
                  const startSec = timing?.start_ms !== undefined ? timing.start_ms / 1000 : (i === 0 ? startTime : 0);
                  const durationSec = isCompleted ? (timing.end_ms! - timing.start_ms) / 1000 : null;

                  return (
                    <div
                      key={uniqueKey}
                      onClick={() => {
                        if (recordingState === 'Recording') {
                          setActiveIndex(i);
                        }
                      }}
                      className={`p-3 rounded-xl border flex items-center justify-between transition cursor-pointer ${
                        isActive
                          ? 'bg-[#1a211f] border-[#f2ca50] shadow-[0_0_12px_rgba(242,202,80,0.15)]'
                          : isCompleted
                          ? 'bg-[#0e1513]/80 border-[#45dfa4]/30 text-[#dde4e0]'
                          : hasStart
                          ? 'bg-[#0e1513]/50 border-[#26332e] text-[#dde4e0]'
                          : 'bg-[#0e1513]/30 border-[#26332e]/50 text-[#94a3b8]'
                      }`}
                    >
                      <div className="flex items-center gap-2.5 min-w-0">
                        <span className={`w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold font-mono shrink-0 ${
                          isCompleted
                            ? 'bg-[#45dfa4]/20 border border-[#45dfa4] text-[#45dfa4] font-bold'
                            : isActive
                            ? 'bg-[#f2ca50] text-[#0e1513]'
                            : 'bg-[#1a211f] text-[#94a3b8]'
                        }`}>
                          {isCompleted ? '✓' : i + 1}
                        </span>
                        <span className="text-xs font-quran font-semibold truncate shrink-0 max-w-[130px] text-right" dir="rtl">
                          {seg.arabic}
                        </span>
                      </div>
                      <div className="flex flex-col items-end text-right">
                        {isCompleted ? (
                          <>
                            <span className="text-xs font-mono font-bold text-[#45dfa4]" dir="ltr">
                              {durationSec !== null ? `${durationSec.toFixed(2)}s` : ''}
                            </span>
                            <span className="text-[10px] font-mono text-[#94a3b8]" dir="ltr">
                              {formatTime(startSec)}
                            </span>
                          </>
                        ) : isActive && hasStart ? (
                          <>
                            <span className="text-xs font-mono font-bold text-[#f2ca50] animate-pulse">
                              {isAr ? 'جاري التسجيل...' : 'Recording...'}
                            </span>
                            <span className="text-[10px] font-mono text-[#94a3b8]" dir="ltr">
                              {formatTime(startSec)}
                            </span>
                          </>
                        ) : (
                          <span className="text-xs font-mono font-bold text-[#94a3b8]/40" dir="ltr">
                            --:--.---
                          </span>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>

          {/* Bottom controls panel */}
          <div className="flex flex-col gap-3 shrink-0 border-t border-[#26332e] pt-3">
            
            {/* Single Clean Centered Action Control Bar */}
            <div className="flex items-center justify-center gap-3 py-1 relative z-10">
              <button
                type="button"
                tabIndex={-1}
                disabled={recordingState !== 'Recording'}
                onClick={resetRecording}
                className="px-4 py-2.5 bg-red-500/15 hover:bg-red-500/25 text-red-400 rounded-xl text-xs font-bold border border-red-500/30 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1.5"
              >
                <span className="material-symbols-outlined text-[16px]">restart_alt</span>
                <span>{isAr ? 'إعادة ضبط (Reset)' : 'Reset'}</span>
              </button>

              <button
                type="button"
                tabIndex={-1}
                disabled={recordingState !== 'Recording' || activeIndex === 0}
                onClick={undoMark}
                className="px-4 py-2.5 bg-[#f2ca50]/15 hover:bg-[#f2ca50]/25 text-[#f2ca50] rounded-xl text-xs font-bold border border-[#f2ca50]/30 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1.5"
              >
                <span className="material-symbols-outlined text-[16px]">undo</span>
                <span>{isAr ? 'تراجع (Backspace)' : 'Undo Mark'}</span>
              </button>

              <button
                type="button"
                tabIndex={-1}
                disabled={recordingState !== 'Recording'}
                onClick={recordTimestamp}
                className="px-7 py-3 bg-gradient-to-r from-[#f2ca50] to-[#ffd768] hover:from-[#ffd768] hover:to-[#f2ca50] text-[#0e1513] font-black rounded-xl shadow-[0_0_20px_rgba(242,202,80,0.35)] text-sm transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2"
              >
                <span className="material-symbols-outlined text-[20px]">bolt</span>
                <span>
                  {activeIndex === approvedSegments.length - 1
                    ? (isAr ? 'إنهاء التسجيل واعتماد التوقيت 🏁' : 'Finish Recording 🏁')
                    : (isAr ? 'تثبيت المقطع والانتقال للتالي (Space) ⚡' : 'Mark Next (Space) ⚡')}
                </span>
              </button>
            </div>

            {/* Audio slider and timer indicators */}
            <div className="flex items-center gap-4 bg-[#131a18] border border-[#26332e] p-3.5 rounded-2xl shadow-inner">
              <button
                type="button"
                tabIndex={-1}
                disabled={recordingState === 'Importing' || recordingState === 'Completed'}
                onClick={togglePlayPause}
                className="w-11 h-11 flex items-center justify-center rounded-full bg-[#f2ca50] hover:bg-[#ffd768] text-[#0e1513] font-bold transition shadow-lg shrink-0 cursor-pointer disabled:opacity-40"
              >
                <span className="material-symbols-outlined text-[24px]">
                  {isPlaying ? 'pause' : 'play_arrow'}
                </span>
              </button>

              <div className="flex-1 flex flex-col gap-1">
                <input
                  type="range"
                  min={0}
                  max={duration || 100}
                  value={currentTime}
                  onChange={(e) => seekTo(Number(e.target.value))}
                  className="w-full h-2 bg-[#1a211f] rounded-full appearance-none cursor-pointer accent-[#f2ca50]"
                  dir="ltr"
                />
                <div className="flex items-center justify-between text-xs font-mono text-[#94a3b8] mt-0.5">
                  <span className="text-white font-bold" dir="ltr">{formatTime(currentTime)}</span>
                  <span dir="ltr">{formatTime(duration)}</span>
                </div>
              </div>
            </div>

            {/* Quick instructions bar */}
            <div className="grid grid-cols-2 md:grid-cols-5 gap-2 bg-[#131a18] border border-[#26332e] p-3 rounded-xl text-[10px] text-[#94a3b8] font-sans leading-relaxed">
              <div className="flex items-center gap-1.5">
                <kbd className="px-1.5 py-0.5 rounded bg-[#1a211f] text-[#f2ca50] font-mono font-bold border border-[#26332e]">Space</kbd>
                <span>{isAr ? 'تثبيت المقطع والتالي' : 'Record & Advance'}</span>
              </div>
              <div className="flex items-center gap-1.5">
                <kbd className="px-1.5 py-0.5 rounded bg-[#1a211f] text-[#f2ca50] font-mono font-bold border border-[#26332e]">Backspace</kbd>
                <span>{isAr ? 'تراجع عن آخر توقيت' : 'Undo & Seek Back'}</span>
              </div>
              <div className="flex items-center gap-1.5">
                <kbd className="px-1.5 py-0.5 rounded bg-[#1a211f] text-[#f2ca50] font-mono font-bold border border-[#26332e]">Shift + R</kbd>
                <span>{isAr ? 'إعادة ضبط التسجيل' : 'Reset All'}</span>
              </div>
              <div className="flex items-center gap-1.5">
                <kbd className="px-1.5 py-0.5 rounded bg-[#1a211f] text-[#f2ca50] font-mono font-bold border border-[#26332e]">← / →</kbd>
                <span>{isAr ? 'تنقل بين المقاطع' : 'Navigate'}</span>
              </div>
              <div className="flex items-center gap-1.5">
                <kbd className="px-1.5 py-0.5 rounded bg-[#1a211f] text-[#f2ca50] font-mono font-bold border border-[#26332e]">Esc</kbd>
                <span>{isAr ? 'إلغاء وإغلاق' : 'Cancel'}</span>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
