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
    <div className="bg-surface-container rounded-xl border border-primary/30 p-5 flex flex-col gap-4 shadow-lg shadow-black/20 font-sans">
      <div className="flex items-center justify-between border-b border-surface-variant/30 pb-3">
        <div className="flex items-center gap-2">
          <span className="p-1 rounded bg-primary/10 text-primary material-symbols-outlined text-[18px]">rocket_launch</span>
          <div>
            <h3 className="text-xs font-bold text-on-surface">
              {isAr ? 'جاهزية التصدير وبدء الإنتاج' : 'Export Readiness & Render Trigger'}
            </h3>
            <p className="text-[10px] text-on-surface-variant">
              {isAr ? 'التحقق المسبق من المتطلبات وبدء معالجة الفيديو' : 'Pre-flight check and video render initialization'}
            </p>
          </div>
        </div>
        <span className={`px-2 py-0.5 rounded text-[10px] font-mono font-bold border ${
          allPrereqsMet
            ? 'bg-secondary/15 text-secondary border-secondary/30'
            : 'bg-primary/15 text-primary border-primary/30'
        }`}>
          {allPrereqsMet ? (isAr ? 'مكتمل ✓' : 'Ready ✓') : (isAr ? 'غير مكتمل ⚠️' : 'Incomplete ⚠️')}
        </span>
      </div>

      {/* Prerequisites Checklist */}
      <div className="bg-surface-container-lowest rounded-xl p-3 border border-surface-variant/30 flex flex-col gap-2">
        <h4 className="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
          {isAr ? 'قائمة الفحص المسبق (Pre-Flight Checklist)' : 'Pre-Flight Checklist'}
        </h4>

        {/* Prepared Check */}
        <div className="flex items-center justify-between text-xs py-1">
          <span className="text-on-surface flex items-center gap-1.5">
            <span className="material-symbols-outlined text-[14px] text-on-surface-variant">menu_book</span>
            <span>{isAr ? 'تجهيز بيانات السورة والرسم العثماني' : 'Dataset prepared (Mushaf glyphs)'}</span>
          </span>
          {isPrepared ? (
            <span className="px-1.5 py-0.5 rounded bg-secondary/15 text-secondary font-bold text-[10px] flex items-center gap-1">
              <span className="material-symbols-outlined text-[12px]">check_circle</span>
              <span>{isAr ? 'جاهز' : 'Ready'}</span>
            </span>
          ) : (
            <span className="px-1.5 py-0.5 rounded bg-red-500/15 text-red-400 font-bold text-[10px] flex items-center gap-1">
              <span className="material-symbols-outlined text-[12px]">cancel</span>
              <span>{isAr ? 'غير جاهز' : 'Missing'}</span>
            </span>
          )}
        </div>

        {/* Audio Check */}
        <div className="flex items-center justify-between text-xs py-1 border-t border-surface-variant/15">
          <span className="text-on-surface flex items-center gap-1.5">
            <span className="material-symbols-outlined text-[14px] text-on-surface-variant">graphic_eq</span>
            <span>{isAr ? 'الملف الصوتي للقارئ المختار' : 'Audio file uploaded'}</span>
          </span>
          {isAudioUploaded ? (
            <span className="px-1.5 py-0.5 rounded bg-secondary/15 text-secondary font-bold text-[10px] flex items-center gap-1">
              <span className="material-symbols-outlined text-[12px]">check_circle</span>
              <span>{isAr ? 'مرفوع' : 'Uploaded'}</span>
            </span>
          ) : (
            <span className="px-1.5 py-0.5 rounded bg-red-500/15 text-red-400 font-bold text-[10px] flex items-center gap-1">
              <span className="material-symbols-outlined text-[12px]">cancel</span>
              <span>{isAr ? 'غير مرفوع' : 'Missing'}</span>
            </span>
          )}
        </div>

        {/* Timings Check */}
        <div className="flex items-center justify-between text-xs py-1 border-t border-surface-variant/15">
          <span className="text-on-surface flex items-center gap-1.5">
            <span className="material-symbols-outlined text-[14px] text-on-surface-variant">timer</span>
            <span>
              {layout === 'reels' 
                ? (isAr ? 'توقيت المقاطع البصرية' : 'Segment Timings')
                : (isAr ? 'توقيت سطور المصحف' : 'Line Timings')
              }
            </span>
          </span>
          {isTimingsReady ? (
            <span className="px-1.5 py-0.5 rounded bg-secondary/15 text-secondary font-bold text-[10px] flex items-center gap-1">
              <span className="material-symbols-outlined text-[12px]">check_circle</span>
              <span>{isWordTimingsImported ? (isAr ? 'مستورد' : 'Imported') : (isAr ? 'جاهز (جلسة العمل)' : 'Ready (Session)')}</span>
            </span>
          ) : (
            <span className="px-1.5 py-0.5 rounded bg-red-500/15 text-red-400 font-bold text-[10px] flex items-center gap-1">
              <span className="material-symbols-outlined text-[12px]">cancel</span>
              <span>{isAr ? 'غير متوفر' : 'Missing'}</span>
            </span>
          )}
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <button
          onClick={handleGenerate}
          disabled={!allPrereqsMet || rendering}
          className={`w-full py-3 px-4 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer ${
            allPrereqsMet && !rendering
              ? 'bg-primary hover:bg-tertiary text-on-primary shadow-[0_0_16px_rgba(242,202,80,0.3)] hover:shadow-[0_0_20px_rgba(242,202,80,0.4)]'
              : 'bg-surface-container-high text-on-surface-variant/40 border border-surface-variant/30 opacity-60 cursor-not-allowed'
          }`}
        >
          {rendering ? (
            <>
              <div className="w-4 h-4 border-2 border-on-primary border-t-transparent rounded-full animate-spin"></div>
              <span>{isAr ? 'جاري بدء معالجة التصدير...' : 'Initializing Render Process...'}</span>
            </>
          ) : (
            <>
              <span className="material-symbols-outlined text-[18px]">movie_creation</span>
              <span>{isAr ? 'بدء التصدير 🎬' : 'Start Video Render 🎬'}</span>
            </>
          )}
        </button>

        {!allPrereqsMet && (
          <span className="text-[11px] text-primary/80 text-center font-mono mt-0.5">
            ⚠️ {isAr ? 'يرجى استيفاء متطلبات الفحص المسبق لتفعيل التصدير.' : 'Please satisfy all checklist items to enable render.'}
          </span>
        )}

        {errorMsg && (
          <div className="bg-red-500/15 text-red-400 border border-red-500/30 px-3 py-2 rounded-xl text-xs font-mono mt-2">
            {errorMsg}
          </div>
        )}
      </div>
    </div>
  );
}
