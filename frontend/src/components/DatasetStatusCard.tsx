'use client';

import React from 'react';
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
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex items-center justify-center min-h-[180px]">
        <div className="flex flex-col items-center gap-2">
          <div className="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full"></div>
          <span className="text-xs text-slate-500 dark:text-slate-400 font-mono">Loading status...</span>
        </div>
      </div>
    );
  }

  if (!status) {
    return (
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 border-dashed rounded-xl p-6 flex items-center justify-center min-h-[180px] text-center">
        <p className="text-sm text-slate-500 dark:text-slate-400">
          No reciter selected yet.
        </p>
      </div>
    );
  }

  const renderTimingMode = (mode: string, timed: number, total: number) => {
    switch (mode) {
      case 'full':
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900">
            Full ({timed}/{total})
          </span>
        );
      case 'partial':
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400 border border-amber-200 dark:border-amber-900">
            Partial ({timed}/{total})
          </span>
        );
      case 'fallback':
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-cyan-50 text-cyan-700 dark:bg-cyan-950/20 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-900">
            Proportional Fallback
          </span>
        );
      case 'none':
      default:
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900">
            None
          </span>
        );
    }
  };

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4">
      <div className="border-b border-slate-100 dark:border-slate-800/60 pb-2">
        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Dataset Status</h3>
      </div>

      <div className="grid grid-cols-2 gap-4">
        {/* Glyphs */}
        <div className="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-xl p-3 flex items-center justify-between">
          <span className="text-xs text-slate-500 dark:text-slate-400 font-semibold">Glyphs</span>
          {status.glyphs ? (
            <span className="text-emerald-500 text-sm font-bold">Available</span>
          ) : (
            <span className="text-red-500 text-sm font-bold">Missing</span>
          )}
        </div>

        {/* Audio */}
        <div className="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-xl p-3 flex items-center justify-between">
          <span className="text-xs text-slate-500 dark:text-slate-400 font-semibold">Audio</span>
          {status.audio ? (
            <span className="text-emerald-500 text-sm font-bold">Available</span>
          ) : (
            <span className="text-red-500 text-sm font-bold">Missing</span>
          )}
        </div>

        {/* Timings */}
        <div className="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-xl p-3 col-span-2 flex items-center justify-between">
          <span className="text-xs text-slate-500 dark:text-slate-400 font-semibold">Timings</span>
          {renderTimingMode(status.timings.mode, status.timings.timedWords, status.timings.totalWords)}
        </div>

        {/* Renderable */}
        <div className="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-xl p-3 col-span-2 flex items-center justify-between">
          <span className="text-sm text-slate-800 dark:text-slate-250 font-bold">Ready to Render</span>
          {status.renderable ? (
            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-bold bg-emerald-600 text-white">
              Ready
            </span>
          ) : (
            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
              Not Ready
            </span>
          )}
        </div>
      </div>
    </div>
  );
}
