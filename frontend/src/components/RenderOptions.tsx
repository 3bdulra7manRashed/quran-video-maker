'use client';

import React from 'react';
import { useT } from '@/hooks/useT';
import { useLanguage } from '@/hooks/useLanguage';
import { LAYOUTS } from '@/config/layouts';

interface RenderOptionsProps {
  layout: string;
  onLayoutChange: (layout: string) => void;
  maxLines: number;
  onMaxLinesChange: (num: number) => void;
  withTranslation: boolean;
  onWithTranslationChange: (val: boolean) => void;
  translationSource: string;
  onTranslationSourceChange: (source: string) => void;
  withTafsir: boolean;
  onWithTafsirChange: (val: boolean) => void;
  useGeneratedContent: boolean;
  onUseGeneratedContentChange: (val: boolean) => void;
  persistToDatabase?: boolean;
  onPersistToDatabaseChange?: (val: boolean) => void;
  useQuranMeTheme?: boolean;
  onUseQuranMeThemeChange?: (val: boolean) => void;
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
  withTafsir,
  onWithTafsirChange,
  useGeneratedContent,
  onUseGeneratedContentChange,
  persistToDatabase = true,
  onPersistToDatabaseChange,
  useQuranMeTheme = false,
  onUseQuranMeThemeChange,
  disabled,
}: RenderOptionsProps) {
  const t = useT();
  const { language } = useLanguage();
  const isAr = language === 'ar';

  const currentLayoutConfig = LAYOUTS[layout];

  return (
    <div className="bg-surface-container rounded-xl border border-surface-variant/40 p-5 flex flex-col gap-4 shadow-sm">
      <div className="flex items-center justify-between border-b border-surface-variant/30 pb-3">
        <div className="flex items-center gap-2">
          <span className="p-1 rounded bg-primary/10 text-primary material-symbols-outlined text-[18px]">aspect_ratio</span>
          <div>
            <h3 className="text-xs font-bold text-on-surface">
              {isAr ? 'تخطيط الفيديو وخيارات الإنتاج' : 'Video Layout & Production Options'}
            </h3>
            <p className="text-[10px] text-on-surface-variant">
              {isAr ? 'أبعاد الفيديو، الترجمة، التفسير، والقالب المخصص' : 'Aspect ratio, translation, tafsir, and theme'}
            </p>
          </div>
        </div>
        <span className="px-2 py-0.5 rounded text-[10px] font-mono bg-primary/15 text-primary border border-primary/30 font-bold uppercase">
          {layout === 'reels' ? '9:16' : '16:9'}
        </span>
      </div>

      <div className="flex flex-col gap-4">
        {/* Layout Cards */}
        <div className="flex flex-col gap-1.5">
          <span className="text-[11px] font-semibold text-on-surface-variant">
            {isAr ? 'تنسيق العرض والأبعاد' : 'Aspect Ratio & Format'}
          </span>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-2.5">
            {/* Reels Option */}
            <button
              type="button"
              disabled={disabled}
              onClick={() => onLayoutChange('reels')}
              className={`p-3 rounded-xl bg-surface-container-lowest border-2 text-start transition-all cursor-pointer flex flex-col gap-1.5 ${
                layout === 'reels'
                  ? 'border-secondary shadow-[0_0_12px_rgba(69,223,164,0.15)] bg-surface-container-high/40'
                  : 'border-surface-variant/30 hover:border-surface-variant/60 opacity-70 hover:opacity-100'
              }`}
            >
              <div className="flex items-center justify-between w-full">
                <span className="text-xs font-bold text-secondary flex items-center gap-1.5">
                  📱 {isAr ? 'ريلز (9:16 Reels)' : 'Reels (9:16)'}
                </span>
                {layout === 'reels' ? (
                  <span className="material-symbols-outlined text-[18px] text-secondary">check_circle</span>
                ) : (
                  <span className="w-3.5 h-3.5 rounded-full border border-surface-variant/60"></span>
                )}
              </div>
              <span className="text-[10px] text-on-surface-variant leading-relaxed">
                {isAr ? 'فيديوهات رأسية قصيرة مخصصة لمنصات التواصل الاجتماعي (TikTok / Instagram / Shorts)' : 'Short vertical videos optimized for social media platforms.'}
              </span>
            </button>

            {/* YouTube Option */}
            <button
              type="button"
              disabled={disabled}
              onClick={() => onLayoutChange('youtube')}
              className={`p-3 rounded-xl bg-surface-container-lowest border-2 text-start transition-all cursor-pointer flex flex-col gap-1.5 ${
                layout === 'youtube'
                  ? 'border-primary shadow-[0_0_12px_rgba(242,202,80,0.15)] bg-surface-container-high/40'
                  : 'border-surface-variant/30 hover:border-surface-variant/60 opacity-70 hover:opacity-100'
              }`}
            >
              <div className="flex items-center justify-between w-full">
                <span className="text-xs font-bold text-primary flex items-center gap-1.5">
                  ▶️ {isAr ? 'يوتيوب (16:9 YouTube)' : 'YouTube (16:9)'}
                </span>
                {layout === 'youtube' ? (
                  <span className="material-symbols-outlined text-[18px] text-primary">check_circle</span>
                ) : (
                  <span className="w-3.5 h-3.5 rounded-full border border-surface-variant/60"></span>
                )}
              </div>
              <span className="text-[10px] text-on-surface-variant leading-relaxed">
                {isAr ? 'عرض صفحة المصحف كاملة والتلاوات الطويلة الأفقية بدقة عالية' : 'Full Mushaf page display and horizontal long-form recitations.'}
              </span>
            </button>
          </div>
        </div>

        {/* Max lines (Conditional for YouTube) */}
        {currentLayoutConfig?.maxLinesSupport && (
          <div className="flex flex-col gap-1.5 border-t border-surface-variant/20 pt-3">
            <label className="text-[11px] font-semibold text-on-surface-variant">{t('render.maxLinesLabel')}</label>
            <div className="grid grid-cols-2 gap-2 bg-surface-container-lowest p-1 rounded-xl border border-surface-variant/30">
              <button
                type="button"
                disabled={disabled}
                onClick={() => onMaxLinesChange(1)}
                className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center justify-center gap-1.5 ${
                  maxLines === 1
                    ? 'bg-[#f2ca50] text-[#131a18] shadow-md font-bold ring-1 ring-[#f2ca50]'
                    : 'text-[#94a3b8] hover:text-[#dde4e0] hover:bg-[#1a211f]'
                }`}
              >
                {maxLines === 1 && <span className="w-1.5 h-1.5 rounded-full bg-[#131a18]"></span>}
                <span>{t('render.oneLinePerScreen')}</span>
              </button>
              <button
                type="button"
                disabled={disabled}
                onClick={() => onMaxLinesChange(2)}
                className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center justify-center gap-1.5 ${
                  maxLines === 2
                    ? 'bg-[#f2ca50] text-[#131a18] shadow-md font-bold ring-1 ring-[#f2ca50]'
                    : 'text-[#94a3b8] hover:text-[#dde4e0] hover:bg-[#1a211f]'
                }`}
              >
                {maxLines === 2 && <span className="w-1.5 h-1.5 rounded-full bg-[#131a18]"></span>}
                <span>{t('render.twoLinesPerScreen')}</span>
              </button>
            </div>
          </div>
        )}

        {/* Translation and Tafsir Settings */}
        <div className="border-t border-surface-variant/20 pt-3 flex flex-col gap-3">
          {/* Translation Switch */}
          {currentLayoutConfig?.translationSupport && (
            <div className="flex flex-col gap-2">
              <label className="flex items-center justify-between p-2.5 rounded-xl bg-surface-container-lowest border border-surface-variant/30 cursor-pointer hover:border-surface-variant/60 transition-colors">
                <div className="flex flex-col">
                  <span className="text-xs font-semibold text-on-surface">
                    {isAr ? 'تضمين الترجمة الإنجليزية' : 'Include English Translation'}
                  </span>
                  <span className="text-[10px] text-on-surface-variant">
                    {t('render.includeTranslationDesc')}
                  </span>
                </div>
                <input
                  type="checkbox"
                  disabled={disabled}
                  checked={withTranslation}
                  onChange={(e) => onWithTranslationChange(e.target.checked)}
                  className="w-4 h-4 rounded border-surface-variant text-primary focus:ring-0 bg-surface-container cursor-pointer"
                />
              </label>

              {withTranslation && (
                <div className="flex flex-col gap-1 px-2">
                  <label htmlFor="translationSource" className="text-[10px] font-semibold text-on-surface-variant uppercase">
                    {t('render.translationSourceLabel')}
                  </label>
                  <select
                    id="translationSource"
                    disabled={disabled}
                    value={translationSource}
                    onChange={(e) => onTranslationSourceChange(e.target.value)}
                    className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface px-3 py-1.5 rounded-lg focus:outline-none focus:border-primary text-xs cursor-pointer"
                  >
                    <option value="sahih_international">{t('render.sahihInternational')}</option>
                  </select>
                </div>
              )}
            </div>
          )}

          {/* Tafsir Switch */}
          {currentLayoutConfig?.tafsirSupport && (
            <label className="flex items-center justify-between p-2.5 rounded-xl bg-surface-container-lowest border border-surface-variant/30 cursor-pointer hover:border-surface-variant/60 transition-colors">
              <div className="flex flex-col">
                <span className="text-xs font-semibold text-on-surface">
                  {isAr ? 'تضمين التفسير الميسر' : 'Include Simplified Tafsir'}
                </span>
                <span className="text-[10px] text-on-surface-variant">
                  {isAr ? 'إدراج نص التفسير للآيات المختارة أسفل الآية' : 'Include the commentary text for selected Ayahs'}
                </span>
              </div>
              <input
                type="checkbox"
                disabled={disabled}
                checked={withTafsir}
                onChange={(e) => onWithTafsirChange(e.target.checked)}
                className="w-4 h-4 rounded border-surface-variant text-primary focus:ring-0 bg-surface-container cursor-pointer"
              />
            </label>
          )}

          {/* Quran.me Theme Toggle */}
          <label className="flex items-center justify-between p-2.5 rounded-xl bg-surface-container-lowest border border-surface-variant/30 cursor-pointer hover:border-primary/50 transition-colors">
            <div className="flex flex-col">
              <span className="text-xs font-semibold text-primary">
                {isAr ? 'قالب Quran.me (ثيم مخصص)' : 'Quran.me Theme (Custom)'}
              </span>
              <span className="text-[10px] text-on-surface-variant">
                {isAr ? 'تلوين الآية القرآنية باللون #4D3126 مع إبقاء التفسير بالأبيض النقي' : 'Render Ayah text in #4D3126 while keeping Tafsir pure white'}
              </span>
            </div>
            <input
              type="checkbox"
              disabled={disabled}
              checked={useQuranMeTheme}
              onChange={(e) => onUseQuranMeThemeChange?.(e.target.checked)}
              className="w-4 h-4 rounded border-surface-variant text-primary focus:ring-0 bg-surface-container cursor-pointer"
            />
          </label>
        </div>

        {/* Advanced Options Section */}
        <div className="border-t border-surface-variant/20 pt-3 flex flex-col gap-2">
          {/* Pre-generated Content */}
          <label className="flex items-center justify-between p-2 rounded-lg bg-surface-container-lowest/60 border border-surface-variant/20 cursor-pointer hover:bg-surface-container-lowest transition-colors">
            <div className="flex flex-col">
              <span className="text-xs font-medium text-on-surface">
                {t('render.useGeneratedContent')}
              </span>
              <span className="text-[10px] text-on-surface-variant">
                {t('render.useGeneratedContentDesc')}
              </span>
            </div>
            <input
              type="checkbox"
              disabled={disabled}
              checked={useGeneratedContent}
              onChange={(e) => onUseGeneratedContentChange(e.target.checked)}
              className="w-4 h-4 rounded border-surface-variant text-primary focus:ring-0 bg-surface-container cursor-pointer"
            />
          </label>

          {/* Persist Segments */}
          <label className="flex items-center justify-between p-2 rounded-lg bg-surface-container-lowest/60 border border-surface-variant/20 cursor-pointer hover:bg-surface-container-lowest transition-colors">
            <div className="flex flex-col">
              <span className="text-xs font-medium text-on-surface">
                {isAr ? 'حفظ كقالب معتمد في قاعدة البيانات' : 'Save permanently to Database'}
              </span>
              <span className="text-[10px] text-on-surface-variant">
                {isAr ? 'حفظ المقاطع والتوقيت كقالب دائم في قاعدة البيانات بدلاً من الجلسة الحالية فقط' : 'Permanently save segment templates in DB instead of current run only'}
              </span>
            </div>
            <input
              type="checkbox"
              disabled={disabled}
              checked={persistToDatabase}
              onChange={(e) => onPersistToDatabaseChange?.(e.target.checked)}
              className="w-4 h-4 rounded border-surface-variant text-primary focus:ring-0 bg-surface-container cursor-pointer"
            />
          </label>
        </div>
      </div>
    </div>
  );
}
