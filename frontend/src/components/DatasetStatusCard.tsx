'use client';

import { DatasetStatus } from '@/services/api';
import { useT } from '@/hooks/useT';

interface DatasetStatusCardProps {
  status: DatasetStatus | null;
  loading: boolean;
}

export default function DatasetStatusCard({ status, loading }: DatasetStatusCardProps) {
  const t = useT();

  if (loading) {
    return (
      <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 flex items-center justify-center min-h-[180px]">
        <div className="flex flex-col items-center gap-3">
          <div className="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
          <span className="text-xs text-zinc-500 font-mono">{t('render.loadingStatus')}</span>
        </div>
      </div>
    );
  }

  if (!status) {
    return (
      <div className="bg-zinc-950 border border-zinc-900 border-dashed rounded-2xl p-6 flex items-center justify-center min-h-[180px] text-center">
        <p className="text-sm text-zinc-500">
          {t('render.selectPrompt')}
        </p>
      </div>
    );
  }

  const renderTimingMode = (mode: string, timed: number, total: number) => {
    switch (mode) {
      case 'full':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            {t('render.timingFull')} ({timed}/{total})
          </span>
        );
      case 'partial':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
            {t('render.timingPartial')} ({timed}/{total})
          </span>
        );
      case 'fallback':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
            {t('render.timingFallback')}
          </span>
        );
      case 'none':
      default:
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400 border border-red-500/20">
            {t('render.timingNone')}
          </span>
        );
    }
  };

  return (
    <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 flex flex-col gap-5 shadow-2xl relative overflow-hidden">
      <div className="absolute top-0 end-0 w-32 h-32 bg-emerald-500/5 blur-3xl pointer-events-none rounded-full"></div>
      
      <div className="flex items-center justify-between border-b border-zinc-900 pb-3">
        <h3 className="font-semibold text-zinc-200">{t('render.datasetStatus')}</h3>
      </div>

      <div className="grid grid-cols-2 gap-4">
        {/* Glyphs */}
        <div className="bg-zinc-900/40 border border-zinc-900/60 rounded-xl p-3 flex items-center justify-between">
          <span className="text-sm text-zinc-400 font-medium">{t('render.glyphs')}</span>
          {status.glyphs ? (
            <span className="text-emerald-500 text-lg">✅</span>
          ) : (
            <span className="text-red-500 text-lg">❌</span>
          )}
        </div>

        {/* Audio */}
        <div className="bg-zinc-900/40 border border-zinc-900/60 rounded-xl p-3 flex items-center justify-between">
          <span className="text-sm text-zinc-400 font-medium">{t('render.audio')}</span>
          {status.audio ? (
            <span className="text-emerald-500 text-lg">✅</span>
          ) : (
            <span className="text-red-500 text-lg">❌</span>
          )}
        </div>

        {/* Timings */}
        <div className="bg-zinc-900/40 border border-zinc-900/60 rounded-xl p-3 col-span-2 flex items-center justify-between">
          <span className="text-sm text-zinc-400 font-medium">{t('render.timings')}</span>
          {renderTimingMode(status.timings.mode, status.timings.timedWords, status.timings.totalWords)}
        </div>

        {/* Renderable */}
        <div className="bg-zinc-900/40 border border-zinc-900/60 rounded-xl p-4 col-span-2 flex items-center justify-between border-t-2 border-t-zinc-800">
          <span className="text-sm text-zinc-300 font-bold">{t('render.readyToRender')}</span>
          {status.renderable ? (
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500 text-black shadow-[0_0_12px_rgba(16,185,129,0.3)]">
              {t('render.ready')} ✅
            </span>
          ) : (
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-zinc-850 text-zinc-400 border border-zinc-850">
              {t('render.notReady')} ❌
            </span>
          )}
        </div>
      </div>
    </div>
  );
}
