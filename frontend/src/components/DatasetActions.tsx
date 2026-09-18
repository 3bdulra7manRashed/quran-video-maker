'use client';

import React, { useRef, useState, useEffect } from 'react';
import { useT } from '@/hooks/useT';
import { useLanguage } from '@/hooks/useLanguage';

import { DatasetStatus } from '@/services/api';

interface DatasetActionsProps {
  onPrepare: () => Promise<void>;
  onUploadAudio: (file: File) => Promise<void>;
  onUploadTimings: (file: File) => Promise<void>;
  disabled: boolean;
  refreshing: boolean;
  status: DatasetStatus | null;
}

export default function DatasetActions({
  onPrepare,
  onUploadAudio,
  onUploadTimings,
  disabled,
  refreshing,
  status,
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

  const { language } = useLanguage();
  const isAr = language === 'ar';

  return (
    <div className="bg-surface-container rounded-xl border border-surface-variant/40 p-4 flex flex-col gap-3 shadow-sm">
      <div className="flex items-center justify-between border-b border-surface-variant/30 pb-2.5">
        <span className="text-xs font-bold text-on-surface flex items-center gap-1.5">
          <span className="material-symbols-outlined text-[16px] text-primary">sync</span>
          <span>{isAr ? 'إجراءات البيانات وتحديث الملفات' : 'Dataset Actions & Sync'}</span>
        </span>
      </div>

      <div className="flex flex-col gap-2.5">
        {/* Prepare Dataset */}
        <button
          onClick={handlePrepare}
          disabled={isButtonDisabled}
          className="w-full py-2.5 px-3 rounded-lg bg-surface-container-high hover:bg-surface-bright text-on-surface border border-surface-variant/50 text-xs font-bold flex items-center justify-center gap-2 transition-all shadow-sm disabled:opacity-40 cursor-pointer"
        >
          {preparing ? (
            <>
              <div className="w-3.5 h-3.5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
              <span>{isAr ? 'جاري تجهيز البيانات...' : 'Preparing Dataset...'}</span>
            </>
          ) : refreshing && lastAction === 'prepare' ? (
            <>
              <div className="w-3.5 h-3.5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
              <span>{isAr ? 'جاري المزامنة...' : 'Syncing...'}</span>
            </>
          ) : (
            <>
              <span className="material-symbols-outlined text-[16px] text-primary">bolt</span>
              <span>{isAr ? 'إعداد وتحديث البيانات ⚡' : 'Prepare & Update Dataset ⚡'}</span>
            </>
          )}
        </button>

        {/* Upload Audio */}
        {status?.glyphs === true && (
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
              className="w-full py-2 px-3 rounded-lg bg-surface-container-lowest hover:bg-surface-container-high text-on-surface border border-surface-variant/30 text-xs font-medium flex items-center justify-center gap-2 transition-colors disabled:opacity-40 cursor-pointer"
            >
              {uploadingAudio ? (
                <>
                  <div className="w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                  <span>{isAr ? 'جاري رفع الملف الصوتي...' : 'Uploading Audio...'}</span>
                </>
              ) : refreshing && lastAction === 'audio' ? (
                <span>{isAr ? 'جاري التحديث...' : 'Refreshing...'}</span>
              ) : (
                <>
                  <span className="material-symbols-outlined text-[16px] text-secondary">audio_file</span>
                  <span>{isAr ? 'رفع الملف الصوتي (.mp3)' : 'Upload Audio (.mp3)'}</span>
                </>
              )}
            </button>
          </div>
        )}

        {/* Upload Timings */}
        {status?.audio === true && (
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
              className="w-full py-2 px-3 rounded-lg bg-surface-container-lowest hover:bg-surface-container-high text-on-surface border border-surface-variant/30 text-xs font-medium flex items-center justify-center gap-2 transition-colors disabled:opacity-40 cursor-pointer"
            >
              {uploadingTimings ? (
                <>
                  <div className="w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                  <span>{isAr ? 'جاري رفع ملف التوقيتات...' : 'Uploading Timings...'}</span>
                </>
              ) : refreshing && lastAction === 'timings' ? (
                <span>{isAr ? 'جاري التحديث...' : 'Refreshing...'}</span>
              ) : (
                <>
                  <span className="material-symbols-outlined text-[16px] text-primary">schedule</span>
                  <span>{isAr ? 'رفع توقيتات Audition (.json)' : 'Upload Timings (.json)'}</span>
                </>
              )}
            </button>
          </div>
        )}
      </div>

      {/* Messages */}
      {message && (
        <div
          className={`px-3 py-2 rounded-xl text-xs font-mono border ${
            message.type === 'success'
              ? 'bg-secondary/10 text-secondary border-secondary/30'
              : 'bg-red-500/10 text-red-400 border-red-500/30'
          }`}
        >
          {message.text}
        </div>
      )}
    </div>
  );
}
