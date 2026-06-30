'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useApi } from '@/context/ApiContext';

interface GenerateVideoCardProps {
  surahNumber: number | '';
  reciterSlug: string;
  scope: 'full' | 'single' | 'range';
  ayahNumber: number;
  fromAyah: number;
  toAyah: number;
  renderable: boolean;
}

export default function GenerateVideoCard({
  surahNumber,
  reciterSlug,
  scope,
  ayahNumber,
  fromAyah,
  toAyah,
  renderable,
}: GenerateVideoCardProps) {
  const { api } = useApi();
  const router = useRouter();

  const [rendering, setRendering] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  if (surahNumber === '' || !reciterSlug) {
    return null;
  }

  const handleGenerate = async () => {
    setRendering(true);
    setErrorMsg(null);

    const params: any = {
      reciter: reciterSlug,
      surah: surahNumber,
      scope,
    };

    if (scope === 'single') {
      params.ayah = ayahNumber;
    } else if (scope === 'range') {
      params.from = fromAyah;
      params.to = toAyah;
    }

    try {
      await api.startRender(params);
      router.push('/jobs');
    } catch (err: any) {
      setErrorMsg(err.message || 'Failed to trigger video generation.');
      console.error(err);
    } finally {
      setRendering(false);
    }
  };

  return (
    <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 flex flex-col gap-4 shadow-2xl relative overflow-hidden">
      <div className="absolute top-0 left-0 w-32 h-32 bg-emerald-500/5 blur-3xl pointer-events-none rounded-full"></div>

      <div className="flex items-center justify-between border-b border-zinc-900 pb-3">
        <h3 className="font-semibold text-zinc-200 font-sans">Generate Video</h3>
        <span className="text-xs text-zinc-500 font-mono">تصدير الفيديو</span>
      </div>

      <div className="flex flex-col gap-2">
        <button
          onClick={handleGenerate}
          disabled={!renderable || rendering}
          className="w-full bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 disabled:bg-emerald-500/10 disabled:text-emerald-500/50 disabled:cursor-not-allowed text-black font-bold px-4 py-3 rounded-xl shadow-[0_0_12px_rgba(16,185,129,0.2)] hover:shadow-[0_0_20px_rgba(16,185,129,0.4)] transition-all active:scale-98 flex items-center justify-center gap-2 cursor-pointer"
        >
          {rendering ? (
            <>
              <div className="w-4 h-4 border-2 border-black border-t-transparent rounded-full animate-spin"></div>
              <span>Queuing Job...</span>
            </>
          ) : (
            <>
              <span>🎬</span>
              <span>Generate Video (بدء التصدير)</span>
            </>
          )}
        </button>

        {!renderable && (
          <span className="text-[10px] text-amber-500 text-center font-mono mt-1">
            ⚠️ Dataset is not renderable yet. Make sure audio and glyphs are available.
          </span>
        )}

        {errorMsg && (
          <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-3 py-2 rounded-xl text-xs font-mono mt-2">
            {errorMsg}
          </div>
        )}
      </div>
    </div>
  );
}
