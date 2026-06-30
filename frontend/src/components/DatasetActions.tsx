'use client';

import React, { useRef, useState } from 'react';

interface DatasetActionsProps {
  onPrepare: () => Promise<void>;
  onUploadAudio: (file: File) => Promise<void>;
  onUploadTimings: (file: File) => Promise<void>;
  disabled: boolean;
}

export default function DatasetActions({ onPrepare, onUploadAudio, onUploadTimings, disabled }: DatasetActionsProps) {
  const audioInputRef = useRef<HTMLInputElement>(null);
  const timingsInputRef = useRef<HTMLInputElement>(null);

  const [preparing, setPreparing] = useState(false);
  const [uploadingAudio, setUploadingAudio] = useState(false);
  const [uploadingTimings, setUploadingTimings] = useState(false);

  const [message, setMessage] = useState<{ text: string; type: 'success' | 'error' } | null>(null);

  const showMessage = (text: string, type: 'success' | 'error') => {
    setMessage({ text, type });
    setTimeout(() => setMessage(null), 5000);
  };

  const handlePrepare = async () => {
    setPreparing(true);
    try {
      await onPrepare();
      showMessage('Dataset prepared successfully.', 'success');
    } catch (err: any) {
      showMessage(err.message || 'Failed to prepare dataset.', 'error');
    } finally {
      setPreparing(false);
    }
  };

  const handleAudioChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploadingAudio(true);
    try {
      await onUploadAudio(file);
      showMessage('Audio uploaded successfully.', 'success');
    } catch (err: any) {
      showMessage(err.message || 'Failed to upload audio.', 'error');
    } finally {
      setUploadingAudio(false);
      if (audioInputRef.current) audioInputRef.current.value = '';
    }
  };

  const handleTimingsChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploadingTimings(true);
    try {
      await onUploadTimings(file);
      showMessage('Manual timings imported successfully.', 'success');
    } catch (err: any) {
      showMessage(err.message || 'Failed to upload timings.', 'error');
    } finally {
      setUploadingTimings(false);
      if (timingsInputRef.current) timingsInputRef.current.value = '';
    }
  };

  return (
    <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 flex flex-col gap-5 shadow-2xl">
      <div className="flex items-center justify-between border-b border-zinc-900 pb-3">
        <h3 className="font-semibold text-zinc-200">Dataset Actions</h3>
        <span className="text-xs text-zinc-500 font-mono">إجراءات البيانات</span>
      </div>

      <div className="flex flex-col gap-3">
        {/* Prepare Dataset */}
        <button
          onClick={handlePrepare}
          disabled={disabled || preparing || uploadingAudio || uploadingTimings}
          className="w-full bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 active:scale-98 border border-emerald-500/30 font-semibold px-4 py-3 rounded-xl transition-all disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center gap-2"
        >
          {preparing ? (
            <>
              <div className="w-4 h-4 border-2 border-emerald-400 border-t-transparent rounded-full animate-spin"></div>
              <span>Preparing...</span>
            </>
          ) : (
            <>
              <span>⚡</span>
              <span>Prepare Dataset (تهيئة البيانات)</span>
            </>
          )}
        </button>

        {/* Upload Audio */}
        <div className="w-full">
          <input
            type="file"
            accept=".mp3"
            ref={audioInputRef}
            onChange={handleAudioChange}
            className="hidden"
          />
          <button
            onClick={() => audioInputRef.current?.click()}
            disabled={disabled || preparing || uploadingAudio || uploadingTimings}
            className="w-full bg-zinc-900 text-zinc-200 hover:bg-zinc-850 active:scale-98 border border-zinc-800 font-semibold px-4 py-3 rounded-xl transition-all disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center gap-2"
          >
            {uploadingAudio ? (
              <>
                <div className="w-4 h-4 border-2 border-zinc-200 border-t-transparent rounded-full animate-spin"></div>
                <span>Uploading Audio...</span>
              </>
            ) : (
              <>
                <span>🎵</span>
                <span>Upload Audio (رفع الملف الصوتي)</span>
              </>
            )}
          </button>
        </div>

        {/* Upload Timings */}
        <div className="w-full">
          <input
            type="file"
            accept=".json"
            ref={timingsInputRef}
            onChange={handleTimingsChange}
            className="hidden"
          />
          <button
            onClick={() => timingsInputRef.current?.click()}
            disabled={disabled || preparing || uploadingAudio || uploadingTimings}
            className="w-full bg-zinc-900 text-zinc-200 hover:bg-zinc-850 active:scale-98 border border-zinc-800 font-semibold px-4 py-3 rounded-xl transition-all disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center gap-2"
          >
            {uploadingTimings ? (
              <>
                <div className="w-4 h-4 border-2 border-zinc-200 border-t-transparent rounded-full animate-spin"></div>
                <span>Uploading Timings...</span>
              </>
            ) : (
              <>
                <span>⏱️</span>
                <span>Upload Timings (رفع ملف التوقيت)</span>
              </>
            )}
          </button>
        </div>
      </div>

      {/* Messages */}
      {message && (
        <div
          className={`px-4 py-3 rounded-xl text-sm border font-mono animate-fade-in ${
            message.type === 'success'
              ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
              : 'bg-red-500/10 text-red-400 border-red-500/20'
          }`}
        >
          {message.text}
        </div>
      )}
    </div>
  );
}
