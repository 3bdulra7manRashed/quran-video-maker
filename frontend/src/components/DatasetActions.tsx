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
    <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 flex flex-col gap-5 shadow-2xl">
      <div className="flex items-center justify-between border-b border-zinc-900 pb-3">
        <h3 className="font-semibold text-zinc-200">{t('render.datasetActions')}</h3>
      </div>

      <div className="flex flex-col gap-3">
        {/* Prepare Dataset */}
        <button
          onClick={handlePrepare}
          disabled={isButtonDisabled}
          className="w-full bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 active:scale-98 border border-emerald-500/30 font-semibold px-4 py-3 rounded-xl transition-all disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center gap-2 cursor-pointer"
        >
          {preparing ? (
            <>
              <div className="w-4 h-4 border-2 border-emerald-400 border-t-transparent rounded-full animate-spin"></div>
              <span>{t('render.preparing')}</span>
            </>
          ) : refreshing && lastAction === 'prepare' ? (
            <>
              <div className="w-4 h-4 border-2 border-emerald-400 border-t-transparent rounded-full animate-spin"></div>
              <span>{t('render.refreshing')}</span>
            </>
          ) : (
            <>
              <span>⚡</span>
              <span>{t('render.prepareDataset')}</span>
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
            disabled={isButtonDisabled}
            className="w-full bg-zinc-900 text-zinc-200 hover:bg-zinc-850 active:scale-98 border border-zinc-800 font-semibold px-4 py-3 rounded-xl transition-all disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center gap-2 cursor-pointer"
          >
            {uploadingAudio ? (
              <>
                <div className="w-4 h-4 border-2 border-zinc-200 border-t-transparent rounded-full animate-spin"></div>
                <span>{t('render.uploadingAudio')}</span>
              </>
            ) : refreshing && lastAction === 'audio' ? (
              <>
                <div className="w-4 h-4 border-2 border-zinc-200 border-t-transparent rounded-full animate-spin"></div>
                <span>{t('render.refreshing')}</span>
              </>
            ) : (
              <>
                <span>🎵</span>
                <span>{t('render.uploadAudio')}</span>
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
            disabled={isButtonDisabled}
            className="w-full bg-zinc-900 text-zinc-200 hover:bg-zinc-850 active:scale-98 border border-zinc-800 font-semibold px-4 py-3 rounded-xl transition-all disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center gap-2 cursor-pointer"
          >
            {uploadingTimings ? (
              <>
                <div className="w-4 h-4 border-2 border-zinc-200 border-t-transparent rounded-full animate-spin"></div>
                <span>{t('render.uploadingTimings')}</span>
              </>
            ) : refreshing && lastAction === 'timings' ? (
              <>
                <div className="w-4 h-4 border-2 border-zinc-200 border-t-transparent rounded-full animate-spin"></div>
                <span>{t('render.refreshing')}</span>
              </>
            ) : (
              <>
                <span>⏱️</span>
                <span>{t('render.uploadTimings')}</span>
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
