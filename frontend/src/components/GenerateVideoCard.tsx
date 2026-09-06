import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useApi } from '@/context/ApiContext';
import { useT } from '@/hooks/useT';
import { useLanguage } from '@/hooks/useLanguage';
import { DatasetStatus } from '@/services/api';

interface GenerateVideoCardProps {
  surahNumber: number | '';
  reciterSlug: string;
  scope: 'full' | 'single' | 'range';
  ayahNumber: number;
  fromAyah: number;
  toAyah: number;
  status: DatasetStatus | null;
  layout: string;
  maxLines: number;
  withTranslation: boolean;
  translationSource: string;
  withTafsir: boolean;
  useGeneratedContent: boolean;
  customJsonPayload?: string;
  useQuranMeTheme?: boolean;
}

export default function GenerateVideoCard({
  surahNumber,
  reciterSlug,
  scope,
  ayahNumber,
  fromAyah,
  toAyah,
  status,
  layout,
  maxLines,
  withTranslation,
  translationSource,
  withTafsir,
  useGeneratedContent,
  customJsonPayload,
  useQuranMeTheme = false,
}: GenerateVideoCardProps) {
  const { api } = useApi();
  const router = useRouter();
  const t = useT();
  const { language } = useLanguage();
  const isAr = language === 'ar';

  const [rendering, setRendering] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  if (surahNumber === '' || !reciterSlug) {
    return null;
  }

  // Calculate prerequisites
  const isPrepared = status?.glyphs ?? false;
  const isAudioUploaded = status?.audio ?? false;
  const hasSessionSegmentTimings = useGeneratedContent && !!customJsonPayload;
  const isWordTimingsImported = (status?.timings?.mode === 'full' || (status?.timings?.timedWords ?? 0) > 0);
  const isLineTimingsImported = status?.timings?.mode === 'partial' || (status?.timings?.mode === 'full' && (status?.timings?.timedWords ?? 0) > 0);

  const isTimingsReady = layout === 'reels' ? (isWordTimingsImported || hasSessionSegmentTimings) : isLineTimingsImported;
  const allPrereqsMet = isPrepared && isAudioUploaded && isTimingsReady;

  const handleGenerate = async () => {
    if (!allPrereqsMet) return;
    
    setRendering(true);
    setErrorMsg(null);

    const params: any = {
      reciter: reciterSlug,
      surah: surahNumber,
      scope,
      layout,
      max_lines: maxLines,
      with_translation: withTranslation,
      translation_source: translationSource,
      with_tafsir: withTafsir,
      use_generated_content: useGeneratedContent,
      custom_json: customJsonPayload || undefined,
      theme: useQuranMeTheme ? 'quran_me' : 'default',
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
      setErrorMsg(err.message || t('render.generateFailed'));
      console.error(err);
    } finally {
      setRendering(false);
    }
  };

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4 font-sans">
      <div>
        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          {isAr ? 'تصدير الفيديو' : 'Render Video'}
        </h3>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 font-sans">
          {isAr ? 'إنشاء الفيديو النهائي باستخدام الإعدادات المحددة.' : 'Generate the final video using the selected configuration.'}
        </p>
      </div>

      {/* Prerequisites Checklist */}
      <div className="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-4 flex flex-col gap-2">
        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
          {isAr ? 'متطلبات التصدير' : 'Export Prerequisites'}
        </h4>

        {/* Prepared Check */}
        <div className="flex items-center justify-between text-xs">
          <span className="text-slate-700 dark:text-slate-350">
            {isAr ? 'تجهيز بيانات السورة' : 'Dataset prepared'}
          </span>
          {isPrepared ? (
            <span className="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
              ✓ {isAr ? 'جاهز' : 'Prepared'}
            </span>
          ) : (
            <span className="text-red-500 font-semibold flex items-center gap-1">
              ✗ {isAr ? 'غير جاهز' : 'Not prepared'}
            </span>
          )}
        </div>

        {/* Audio Check */}
        <div className="flex items-center justify-between text-xs border-t border-slate-100 dark:border-slate-900/60 pt-2">
          <span className="text-slate-700 dark:text-slate-350">
            {isAr ? 'رفع الملف الصوتي' : 'Audio uploaded'}
          </span>
          {isAudioUploaded ? (
            <span className="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
              ✓ {isAr ? 'مرفوع' : 'Uploaded'}
            </span>
          ) : (
            <span className="text-red-500 font-semibold flex items-center gap-1">
              ✗ {isAr ? 'غير مرفوع' : 'Not uploaded'}
            </span>
          )}
        </div>

        {/* Timings Check */}
        <div className="flex items-center justify-between text-xs border-t border-slate-100 dark:border-slate-900/60 pt-2">
          <span className="text-slate-700 dark:text-slate-350">
            {layout === 'reels' 
              ? (isAr ? 'استيراد توقيت المقاطع' : 'Segment Timings imported')
              : (isAr ? 'استيراد توقيت السطور' : 'Line Timings imported')
            }
          </span>
          {isTimingsReady ? (
            <span className="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
              ✓ {isWordTimingsImported ? (isAr ? 'مستورد' : 'Imported') : (isAr ? 'جاهز (جلسة العمل)' : 'Ready (Session)')}
            </span>
          ) : (
            <span className="text-red-500 font-semibold flex items-center gap-1">
              ✗ {layout === 'reels' 
                ? (isAr ? 'غير مستورد' : 'Segment Timings not imported')
                : (isAr ? 'غير مستورد' : 'Line Timings not imported')
              }
            </span>
          )}
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <button
          onClick={handleGenerate}
          disabled={!allPrereqsMet || rendering}
          className="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 disabled:bg-emerald-700/20 disabled:text-emerald-500/50 disabled:cursor-not-allowed text-white font-bold px-4 py-3 rounded-xl transition-none flex items-center justify-center gap-2 cursor-pointer text-sm"
        >
          {rendering ? (
            <span>{isAr ? 'جاري بدء التصدير...' : 'Processing Render...'}</span>
          ) : (
            <span>{isAr ? '🎬 بدء التصدير' : '🎬 Render Video'}</span>
          )}
        </button>

        {!allPrereqsMet && (
          <span className="text-[10px] text-amber-600 dark:text-amber-400 text-center font-mono mt-1">
            ⚠️ {isAr ? 'برجاء استيفاء جميع المتطلبات لتفعيل زر التصدير.' : 'Please satisfy all prerequisites to enable video export.'}
          </span>
        )}

        {errorMsg && (
          <div className="bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900/50 px-3 py-2 rounded-xl text-xs font-mono mt-2">
            {errorMsg}
          </div>
        )}
      </div>
    </div>
  );
}
