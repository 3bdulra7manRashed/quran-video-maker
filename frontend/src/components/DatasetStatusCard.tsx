'use client';

import React from 'react';
import { DatasetStatus } from '@/services/api';
import { useT } from '@/hooks/useT';
import { useLanguage } from '@/hooks/useLanguage';

interface DatasetStatusCardProps {
  status: DatasetStatus | null;
  loading: boolean;
}

export default function DatasetStatusCard({ status, loading }: DatasetStatusCardProps) {
  const t = useT();

  const { language } = useLanguage();
  const isAr = language === 'ar';

  if (loading && !status) {
    return (
      <div className="bg-surface-container border border-surface-variant/40 rounded-xl p-6 flex items-center justify-center min-h-[180px]">
        <div className="flex flex-col items-center gap-2">
          <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
          <span className="text-xs text-on-surface-variant font-mono">
            {isAr ? 'جاري فحص حالة البيانات...' : 'Checking dataset status...'}
          </span>
        </div>
      </div>
    );
  }

  if (!status) {
    return (
      <div className="bg-surface-container border border-surface-variant/40 border-dashed rounded-xl p-6 flex items-center justify-center min-h-[180px] text-center">
        <p className="text-xs text-on-surface-variant">
          {isAr ? 'لم يتم اختيار القارئ والسورة بعد.' : 'No reciter or Surah selected yet.'}
        </p>
      </div>
    );
  }

  // Calculate readiness score out of 5
  let readyCount = 0;
  if (status.audio) readyCount++;
  if (status.glyphs) readyCount++;
  if (status.timings.mode !== 'none') readyCount++;
  if (status.translations?.ready) readyCount++;
  if (status.approved_segments && status.approved_segments.length > 0) readyCount++;

  const renderTimingBadge = (mode: string, timed: number, total: number) => {
    switch (mode) {
      case 'full':
        return (
          <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-secondary/15 text-secondary border border-secondary/30">
            {isAr ? `كامل (${timed}/${total})` : `Full (${timed}/${total})`}
          </span>
        );
      case 'partial':
        return (
          <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-primary/15 text-primary border border-primary/30">
            {isAr ? `جزئي (${timed}/${total})` : `Partial (${timed}/${total})`}
          </span>
        );
      case 'fallback':
        return (
          <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-cyan-500/15 text-cyan-400 border border-cyan-500/30">
            {isAr ? 'نسبي تلقائي' : 'Fallback'}
          </span>
        );
      case 'none':
      default:
        return (
          <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-500/15 text-red-400 border border-red-500/30">
            {isAr ? 'غير متوفر' : 'Missing'}
          </span>
        );
    }
  };

  return (
    <div className="bg-surface-container rounded-xl border border-surface-variant/40 p-4 flex flex-col gap-3 shadow-sm">
      {/* Header with 5/5 score */}
      <div className="flex items-center justify-between border-b border-surface-variant/30 pb-2.5">
        <span className="text-xs font-bold text-on-surface flex items-center gap-1.5">
          <span className="material-symbols-outlined text-[16px] text-primary">database</span>
          <span>{isAr ? 'حالة البيانات ومصفوفة الجاهزية' : 'Dataset Status Matrix'}</span>
        </span>
        <div className="flex items-center gap-2">
          {loading && (
            <div className="w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
          )}
          <span className={`px-2 py-0.5 rounded text-[10px] font-mono font-bold border ${
            readyCount >= 4
              ? 'bg-secondary/15 text-secondary border-secondary/30'
              : 'bg-primary/15 text-primary border-primary/30'
          }`}>
            {readyCount}/5 {isAr ? 'جاهزة' : 'Ready'}
          </span>
        </div>
      </div>

      {/* 2x2 Grid of Status Pills */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
        {/* Audio */}
        <div className="p-2 rounded-lg bg-surface-container-lowest flex items-center justify-between border border-surface-variant/25">
          <span className="text-on-surface-variant flex items-center gap-1">
            <span className="material-symbols-outlined text-[14px]">graphic_eq</span>
            <span>{isAr ? 'الملف الصوتي:' : 'Audio File:'}</span>
          </span>
          {status.audio ? (
            <span className="px-1.5 py-0.5 rounded bg-secondary/15 text-secondary font-bold text-[10px]">
              {isAr ? 'متوفر' : 'Available'}
            </span>
          ) : (
            <span className="px-1.5 py-0.5 rounded bg-red-500/15 text-red-400 font-bold text-[10px]">
              {isAr ? 'مفقود' : 'Missing'}
            </span>
          )}
        </div>

        {/* Glyphs */}
        <div className="p-2 rounded-lg bg-surface-container-lowest flex items-center justify-between border border-surface-variant/25">
          <span className="text-on-surface-variant flex items-center gap-1">
            <span className="material-symbols-outlined text-[14px]">menu_book</span>
            <span>{isAr ? 'الرسم العثماني:' : 'Mushaf Glyphs:'}</span>
          </span>
          {status.glyphs ? (
            <span className="px-1.5 py-0.5 rounded bg-secondary/15 text-secondary font-bold text-[10px]">
              {isAr ? 'متوفر' : 'Available'}
            </span>
          ) : (
            <span className="px-1.5 py-0.5 rounded bg-red-500/15 text-red-400 font-bold text-[10px]">
              {isAr ? 'مفقود' : 'Missing'}
            </span>
          )}
        </div>

        {/* Timings */}
        <div className="p-2 rounded-lg bg-surface-container-lowest flex items-center justify-between border border-surface-variant/25">
          <span className="text-on-surface-variant flex items-center gap-1">
            <span className="material-symbols-outlined text-[14px]">timer</span>
            <span>{isAr ? 'توقيتات Audition:' : 'Audition Timings:'}</span>
          </span>
          {renderTimingBadge(status.timings.mode, status.timings.timedWords, status.timings.totalWords)}
        </div>

        {/* Translation */}
        <div className="p-2 rounded-lg bg-surface-container-lowest flex items-center justify-between border border-surface-variant/25">
          <span className="text-on-surface-variant flex items-center gap-1">
            <span className="material-symbols-outlined text-[14px]">translate</span>
            <span>{isAr ? 'الترجمة الإنجليزية:' : 'Translation:'}</span>
          </span>
          {status.translations?.ready ? (
            <span className="px-1.5 py-0.5 rounded bg-secondary/15 text-secondary font-bold text-[10px]">
              {isAr ? 'متوفر (Saheeh)' : 'Ready (Saheeh)'}
            </span>
          ) : (
            <span className="px-1.5 py-0.5 rounded bg-red-500/15 text-red-400 font-bold text-[10px]">
              {isAr ? 'غير متوفر' : 'Unavailable'}
            </span>
          )}
        </div>
      </div>

      {/* Approved Segments Count */}
      <div className="p-2 rounded-lg bg-surface-container-lowest flex items-center justify-between border border-primary/30">
        <span className="text-on-surface text-[11px] font-semibold flex items-center gap-1">
          <span className="material-symbols-outlined text-[14px] text-primary">auto_awesome</span>
          <span>{isAr ? 'المقاطع المعتمدة للإنتاج:' : 'Approved AI Segments:'}</span>
        </span>
        {status.approved_segments && status.approved_segments.length > 0 ? (
          <span className="px-2 py-0.5 rounded bg-primary/20 text-primary font-bold text-xs font-mono">
            {status.approved_segments.length} {isAr ? 'مقطع جاهز' : 'segments ready'}
          </span>
        ) : (
          <span className="px-2 py-0.5 rounded bg-surface-container text-on-surface-variant text-[10px] font-mono">
            {isAr ? 'لا يوجد (توليد الذكاء مطلوب)' : 'None (AI generation needed)'}
          </span>
        )}
      </div>

      {/* Readiness Status Footer */}
      <div className="p-2 rounded-lg bg-surface-container-lowest flex items-center justify-between border border-surface-variant/25">
        <span className="text-on-surface text-[11px] font-semibold">
          {isAr ? 'حالة الجاهزية للتصدير:' : 'Ready to Render:'}
        </span>
        {status.renderable ? (
          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-secondary text-on-secondary shadow-sm flex items-center gap-1">
            <span className="material-symbols-outlined text-[12px]">check</span>
            <span>{isAr ? 'جاهز للتصدير ✓' : 'Ready ✓'}</span>
          </span>
        ) : (
          <span className="px-2 py-0.5 rounded text-[10px] font-medium bg-surface-container text-on-surface-variant">
            {isAr ? 'في انتظار استكمال المتطلبات' : 'Pending requirements'}
          </span>
        )}
      </div>
    </div>
  );
}
