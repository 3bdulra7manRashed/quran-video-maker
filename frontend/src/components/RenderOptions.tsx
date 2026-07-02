'use client';

import React from 'react';
import { useT } from '@/hooks/useT';
import { useLanguage } from '@/hooks/useLanguage';

interface RenderOptionsProps {
  layout: string;
  onLayoutChange: (layout: string) => void;
  maxLines: number;
  onMaxLinesChange: (num: number) => void;
  withTranslation: boolean;
  onWithTranslationChange: (val: boolean) => void;
  translationSource: string;
  onTranslationSourceChange: (source: string) => void;
  useGeneratedContent: boolean;
  onUseGeneratedContentChange: (val: boolean) => void;
  disabled: boolean;
}

export default function RenderOptions({
  layout,
  onLayoutChange,
  maxLines,
  onMaxLinesChange,
  withTranslation,
  onWithTranslationChange,
  translationSource,
  onTranslationSourceChange,
  useGeneratedContent,
  onUseGeneratedContentChange,
  disabled,
}: RenderOptionsProps) {
  const t = useT();
  const { language } = useLanguage();
  const isAr = language === 'ar';

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4">
      <div>
        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          {isAr ? 'تخطيط الفيديو' : 'Layout Selection'}
        </h3>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 font-sans">
          {isAr ? 'اختر طريقة عرض النص القرآني على الشاشة.' : 'Choose how the Quran text will be displayed.'}
        </p>
      </div>

      <div className="flex flex-col gap-4">
        {/* Layout Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <button
            type="button"
            disabled={disabled}
            onClick={() => onLayoutChange('reels')}
            className={`p-4 rounded-xl border text-left flex flex-col gap-1 cursor-pointer transition-none ${
              layout === 'reels'
                ? 'border-emerald-500 dark:border-emerald-400 bg-emerald-50/10 dark:bg-emerald-950/20'
                : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700'
            }`}
          >
            <span className="font-semibold text-slate-900 dark:text-slate-100">
              {isAr ? '📱 ريلز' : '📱 Reels'}
            </span>
            <span className="text-xs text-slate-500 dark:text-slate-400">
              {isAr ? 'فيديوهات رأسية قصيرة مخصصة لمنصات التواصل الاجتماعي.' : 'Short vertical videos optimized for social platforms.'}
            </span>
          </button>
          
          <button
            type="button"
            disabled={disabled}
            onClick={() => onLayoutChange('youtube')}
            className={`p-4 rounded-xl border text-left flex flex-col gap-1 cursor-pointer transition-none ${
              layout === 'youtube'
                ? 'border-emerald-500 dark:border-emerald-400 bg-emerald-50/10 dark:bg-emerald-950/20'
                : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700'
            }`}
          >
            <span className="font-semibold text-slate-900 dark:text-slate-100">
              {isAr ? '▶️ يوتيوب' : '▶️ YouTube'}
            </span>
            <span className="text-xs text-slate-500 dark:text-slate-400">
              {isAr ? 'عرض صفحة المصحف كاملة والتلاوات الطويلة.' : 'Full Mushaf and long-form recitations.'}
            </span>
          </button>
        </div>

        {/* Max lines (YouTube only) */}
        {layout === 'youtube' && (
          <div className="flex flex-col gap-1.5 border-t border-slate-100 dark:border-slate-800/60 pt-4">
            <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">{t('render.maxLinesLabel')}</label>
            <div className="grid grid-cols-2 gap-2 bg-slate-50 dark:bg-slate-950 p-1 rounded-xl border border-slate-200 dark:border-slate-800">
              <button
                type="button"
                disabled={disabled}
                onClick={() => onMaxLinesChange(1)}
                className={`py-2 px-3 rounded-lg text-xs font-semibold ${
                  maxLines === 1
                    ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-sm border border-slate-200 dark:border-slate-700'
                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'
                }`}
              >
                {t('render.oneLinePerScreen')}
              </button>
              <button
                type="button"
                disabled={disabled}
                onClick={() => onMaxLinesChange(2)}
                className={`py-2 px-3 rounded-lg text-xs font-semibold ${
                  maxLines === 2
                    ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-sm border border-slate-200 dark:border-slate-700'
                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'
                }`}
              >
                {t('render.twoLinesPerScreen')}
              </button>
            </div>
          </div>
        )}

        {/* Translation Settings */}
        <div className="border-t border-slate-100 dark:border-slate-800/60 pt-4 flex flex-col gap-3">
          <div className="flex items-center justify-between">
            <div className="flex flex-col">
              <label htmlFor="withTranslation" className="text-xs text-slate-700 dark:text-slate-350 font-semibold cursor-pointer">
                {t('render.includeTranslation')}
              </label>
              <span className="text-[10px] text-slate-500 dark:text-slate-400">{t('render.includeTranslationDesc')}</span>
            </div>
            <input
              id="withTranslation"
              type="checkbox"
              disabled={disabled}
              checked={withTranslation}
              onChange={(e) => onWithTranslationChange(e.target.checked)}
              className="w-4 h-4 accent-emerald-600 rounded bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 cursor-pointer disabled:opacity-40"
            />
          </div>

          {withTranslation && (
            <div className="flex flex-col gap-1.5">
              <label htmlFor="translationSource" className="text-xs text-slate-500 dark:text-slate-400">
                {t('render.translationSourceLabel')}
              </label>
              <select
                id="translationSource"
                disabled={disabled}
                value={translationSource}
                onChange={(e) => onTranslationSourceChange(e.target.value)}
                className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 font-sans text-xs cursor-pointer"
              >
                <option value="sahih_international">{t('render.sahihInternational')}</option>
              </select>
            </div>
          )}
        </div>

        {/* Force Pre-generated Content Settings */}
        <div className="border-t border-slate-100 dark:border-slate-800/60 pt-4 flex items-center justify-between">
          <div className="flex flex-col">
            <label htmlFor="useGeneratedContent" className="text-xs text-slate-700 dark:text-slate-350 font-semibold cursor-pointer">
              {t('render.useGeneratedContent')}
            </label>
            <span className="text-[10px] text-slate-500 dark:text-slate-400">{t('render.useGeneratedContentDesc')}</span>
          </div>
          <input
            id="useGeneratedContent"
            type="checkbox"
            disabled={disabled}
            checked={useGeneratedContent}
            onChange={(e) => onUseGeneratedContentChange(e.target.checked)}
            className="w-4 h-4 accent-emerald-600 rounded bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 cursor-pointer disabled:opacity-40"
          />
        </div>
      </div>
    </div>
  );
}
