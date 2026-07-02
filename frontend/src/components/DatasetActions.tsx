'use client';

import React, { useRef, useState, useEffect } from 'react';
import { useT } from '@/hooks/useT';

interface DatasetActionsProps {
  onPrepare: () => Promise<void>;
  onUploadAudio: (file: File) => Promise<void>;
  onUploadTimings: (file: File) => Promise<void>;
  disabled: boolean;
  refreshing: boolean;
}

export default function DatasetActions({
  onPrepare,
  onUploadAudio,
  onUploadTimings,
  disabled,
  refreshing,
}: DatasetActionsProps) {
  const t = useT();
  const audioInputRef = useRef<HTMLInputElement>(null);
  const timingsInputRef = useRef<HTMLInputElement>(null);

  const [preparing, setPreparing] = useState(false);
  const [uploadingAudio, setUploadingAudio] = useState(false);
  const [uploadingTimings, setUploadingTimings] = useState(false);
  const [lastAction, setLastAction] = useState<'prepare' | 'audio' | 'timings' | null>(null);

  const [message, setMessage] = useState<{ text: string; type: 'success' | 'error' } | null>(null);

  const showMessage = (text: string, type: 'success' | 'error') => {
    setMessage({ text, type });
    setTimeout(() => setMessage(null), 5000);
  };

  // Reset last action when refreshing finishes
  useEffect(() => {
    if (!refreshing) {
      setLastAction(null);
    }
  }, [refreshing]);

  const handlePrepare = async () => {
    setPreparing(true);
    setLastAction('prepare');
    try {
      await onPrepare();
      showMessage(t('render.prepareSuccess'), 'success');
    } catch (err: any) {
      showMessage(err.message || t('render.prepareFailed'), 'error');
      setLastAction(null);
    } finally {
      setPreparing(false);
    }
  };

  const handleAudioChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploadingAudio(true);
    setLastAction('audio');
    try {
      await onUploadAudio(file);
      showMessage(t('render.audioUploadSuccess'), 'success');
    } catch (err: any) {
      showMessage(err.message || t('render.audioUploadFailed'), 'error');
      setLastAction(null);
    } finally {
      setUploadingAudio(false);
      if (audioInputRef.current) audioInputRef.current.value = '';
    }
  };

  const handleTimingsChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploadingTimings(true);
    setLastAction('timings');
    try {
      await onUploadTimings(file);
      showMessage(t('render.timingsUploadSuccess'), 'success');
    } catch (err: any) {
      showMessage(err.message || t('render.timingsUploadFailed'), 'error');
      setLastAction(null);
    } finally {
      setUploadingTimings(false);
      if (timingsInputRef.current) timingsInputRef.current.value = '';
    }
  };

  const isButtonDisabled = disabled || preparing || uploadingAudio || uploadingTimings || refreshing;

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4">
      <div className="border-b border-slate-100 dark:border-slate-800/60 pb-2">
        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Dataset Actions</h3>
      </div>

      <div className="flex flex-col gap-3">
        {/* Prepare Dataset */}
        <button
          onClick={handlePrepare}
          disabled={isButtonDisabled}
          className="w-full bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 dark:hover:bg-slate-200 text-white dark:text-slate-900 font-semibold px-4 py-2.5 rounded-xl text-xs disabled:opacity-40 flex items-center justify-center gap-2 cursor-pointer"
        >
          {preparing ? (
            <span>Preparing...</span>
          ) : refreshing && lastAction === 'prepare' ? (
            <span>Refreshing...</span>
          ) : (
            <span>⚡ Prepare Dataset</span>
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
            disabled={isButtonDisabled}
            className="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 font-semibold px-4 py-2.5 rounded-xl text-xs disabled:opacity-40 flex items-center justify-center gap-2 cursor-pointer"
          >
            {uploadingAudio ? (
              <span>Uploading Audio...</span>
            ) : refreshing && lastAction === 'audio' ? (
              <span>Refreshing...</span>
            ) : (
              <span>🎵 Upload Audio (.mp3)</span>
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
            disabled={isButtonDisabled}
            className="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 font-semibold px-4 py-2.5 rounded-xl text-xs disabled:opacity-40 flex items-center justify-center gap-2 cursor-pointer"
          >
            {uploadingTimings ? (
              <span>Uploading Timings...</span>
            ) : refreshing && lastAction === 'timings' ? (
              <span>Refreshing...</span>
            ) : (
              <span>⏱️ Upload Timings (.json)</span>
            )}
          </button>
        </div>
      </div>

      {/* Messages */}
      {message && (
        <div
          className={`px-3 py-2 rounded-xl text-xs font-mono border ${
            message.type === 'success'
              ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900'
              : 'bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400 border-red-200 dark:border-red-900'
          }`}
        >
          {message.text}
        </div>
      )}
    </div>
  );
}
