import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useApi } from '@/context/ApiContext';
import { useT } from '@/hooks/useT';
import { useLanguage } from '@/hooks/useLanguage';

interface GenerateVideoCardProps {
  surahNumber: number | '';
  reciterSlug: string;
  scope: 'full' | 'single' | 'range';
  ayahNumber: number;
  fromAyah: number;
  toAyah: number;
  renderable: boolean;
  layout: string;
  maxLines: number;
  withTranslation: boolean;
  translationSource: string;
  useGeneratedContent: boolean;
  customJsonPayload?: string;
}

export default function GenerateVideoCard({
  surahNumber,
  reciterSlug,
  scope,
  ayahNumber,
  fromAyah,
  toAyah,
  renderable,
  layout,
  maxLines,
  withTranslation,
  translationSource,
  useGeneratedContent,
  customJsonPayload,
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

  const handleGenerate = async () => {
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
      use_generated_content: useGeneratedContent,
      custom_json: customJsonPayload || undefined,
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
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4">
      <div>
        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          {isAr ? 'تصدير الفيديو' : 'Render Video'}
        </h3>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 font-sans">
          {isAr ? 'إنشاء الفيديو النهائي باستخدام الإعدادات المحددة.' : 'Generate the final video using the selected configuration.'}
        </p>
      </div>

      <div className="flex flex-col gap-2">
        <button
          onClick={handleGenerate}
          disabled={!renderable || rendering}
          className="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 disabled:bg-emerald-700/20 disabled:text-emerald-500/50 disabled:cursor-not-allowed text-white font-bold px-4 py-3 rounded-xl transition-none flex items-center justify-center gap-2 cursor-pointer text-sm"
        >
          {rendering ? (
            <span>{isAr ? 'جاري بدء التصدير...' : 'Processing Render...'}</span>
          ) : (
            <span>{isAr ? '🎬 بدء التصدير' : '🎬 Render Video'}</span>
          )}
        </button>

        {!renderable && (
          <span className="text-[10px] text-amber-600 dark:text-amber-400 text-center font-mono mt-1">
            ⚠️ {t('render.datasetNotRenderable')}
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
