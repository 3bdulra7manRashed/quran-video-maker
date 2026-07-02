'use client';

import React from 'react';
import { useT } from '@/hooks/useT';

export type RenderScope = 'full' | 'single' | 'range';

interface RenderOptionsProps {
  scope: RenderScope;
  onScopeChange: (scope: RenderScope) => void;
  ayahNumber: number;
  onAyahNumberChange: (num: number) => void;
  fromAyah: number;
  onFromAyahChange: (num: number) => void;
  toAyah: number;
  onToAyahChange: (num: number) => void;
  maxAyahs: number;
  disabled: boolean;
  layout: string;
  onLayoutChange: (layout: string) => void;
  maxLines: number;
  onMaxLinesChange: (num: number) => void;
  withTranslation: boolean;
  onWithTranslationChange: (val: boolean) => void;
  translationSource: string;
  onTranslationSourceChange: (source: string) => void;
}

export default function RenderOptions({
  scope,
  onScopeChange,
  ayahNumber,
  onAyahNumberChange,
  fromAyah,
  onFromAyahChange,
  toAyah,
  onToAyahChange,
  maxAyahs,
  disabled,
  layout,
  onLayoutChange,
  maxLines,
  onMaxLinesChange,
  withTranslation,
  onWithTranslationChange,
  translationSource,
  onTranslationSourceChange,
}: RenderOptionsProps) {
  const t = useT();

  return (
    <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 flex flex-col gap-5 shadow-2xl">
      <div className="flex items-center justify-between border-b border-zinc-900 pb-3">
        <h3 className="font-semibold text-zinc-200">{t('render.renderOptions')}</h3>
      </div>

      <div className="flex flex-col gap-4">
        {/* Scope Selector */}
        <div className="grid grid-cols-3 gap-2 bg-zinc-900/50 p-1 rounded-xl border border-zinc-900">
          <button
            type="button"
            disabled={disabled}
            onClick={() => onScopeChange('full')}
            className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all ${
              scope === 'full'
                ? 'bg-zinc-800 text-zinc-100 shadow'
                : 'text-zinc-500 hover:text-zinc-350'
            }`}
          >
            {t('render.scopeFull')}
          </button>
          <button
            type="button"
            disabled={disabled}
            onClick={() => onScopeChange('single')}
            className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all ${
              scope === 'single'
                ? 'bg-zinc-800 text-zinc-100 shadow'
                : 'text-zinc-500 hover:text-zinc-350'
            }`}
          >
            {t('render.scopeSingle')}
          </button>
          <button
            type="button"
            disabled={disabled}
            onClick={() => onScopeChange('range')}
            className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all ${
              scope === 'range'
                ? 'bg-zinc-800 text-zinc-100 shadow'
                : 'text-zinc-500 hover:text-zinc-350'
            }`}
          >
            {t('render.scopeRange')}
          </button>
        </div>

        {/* Conditional Inputs */}
        {scope === 'single' && (
          <div className="flex flex-col gap-2">
            <label className="text-xs text-zinc-500 font-mono">{t('render.ayahNumberLabel', { max: maxAyahs })}</label>
            <input
              type="number"
              min={1}
              max={maxAyahs}
              disabled={disabled}
              value={ayahNumber}
              onChange={(e) => onAyahNumberChange(Math.max(1, Math.min(maxAyahs, Number(e.target.value))))}
              className="w-full bg-zinc-900 border border-zinc-800 text-zinc-200 px-3 py-2 rounded-xl focus:outline-none focus:border-zinc-700 transition-colors font-mono"
            />
          </div>
        )}

        {scope === 'range' && (
          <div className="grid grid-cols-2 gap-4">
            <div className="flex flex-col gap-2">
              <label className="text-xs text-zinc-500 font-mono">{t('render.fromLabel')}</label>
              <input
                type="number"
                min={1}
                max={toAyah}
                disabled={disabled}
                value={fromAyah}
                onChange={(e) => onFromAyahChange(Math.max(1, Math.min(toAyah, Number(e.target.value))))}
                className="w-full bg-zinc-900 border border-zinc-800 text-zinc-200 px-3 py-2 rounded-xl focus:outline-none focus:border-zinc-700 transition-colors font-mono"
              />
            </div>
            <div className="flex flex-col gap-2">
              <label className="text-xs text-zinc-500 font-mono">{t('render.toLabel', { max: maxAyahs })}</label>
              <input
                type="number"
                min={fromAyah}
                max={maxAyahs}
                disabled={disabled}
                value={toAyah}
                onChange={(e) => onToAyahChange(Math.max(fromAyah, Math.min(maxAyahs, Number(e.target.value))))}
                className="w-full bg-zinc-900 border border-zinc-800 text-zinc-200 px-3 py-2 rounded-xl focus:outline-none focus:border-zinc-700 transition-colors font-mono"
              />
            </div>
          </div>
        )}

        {/* Layout & Style Selection */}
        <div className="border-t border-zinc-900 pt-4 flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <label className="text-xs text-zinc-500 font-mono">{t('render.videoLayout')}</label>
            <div className="grid grid-cols-2 gap-2 bg-zinc-900/50 p-1 rounded-xl border border-zinc-900">
              <button
                type="button"
                disabled={disabled}
                onClick={() => onLayoutChange('reels')}
                className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all ${
                  layout === 'reels'
                    ? 'bg-zinc-800 text-zinc-100 shadow'
                    : 'text-zinc-500 hover:text-zinc-350'
                }`}
              >
                {t('render.layoutReels')}
              </button>
              <button
                type="button"
                disabled={disabled}
                onClick={() => onLayoutChange('youtube')}
                className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all ${
                  layout === 'youtube'
                    ? 'bg-zinc-800 text-zinc-100 shadow'
                    : 'text-zinc-500 hover:text-zinc-350'
                }`}
              >
                {t('render.layoutYoutube')}
              </button>
            </div>
          </div>

          {layout === 'youtube' && (
            <div className="flex flex-col gap-2">
              <label className="text-xs text-zinc-500 font-mono">{t('render.maxLinesLabel')}</label>
              <div className="grid grid-cols-2 gap-2 bg-zinc-900/50 p-1 rounded-xl border border-zinc-900">
                <button
                  type="button"
                  disabled={disabled}
                  onClick={() => onMaxLinesChange(1)}
                  className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all ${
                    maxLines === 1
                      ? 'bg-zinc-800 text-zinc-100 shadow'
                      : 'text-zinc-500 hover:text-zinc-350'
                  }`}
                >
                  {t('render.oneLinePerScreen')}
                </button>
                <button
                  type="button"
                  disabled={disabled}
                  onClick={() => onMaxLinesChange(2)}
                  className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all ${
                    maxLines === 2
                      ? 'bg-zinc-800 text-zinc-100 shadow'
                      : 'text-zinc-500 hover:text-zinc-350'
                  }`}
                >
                  {t('render.twoLinesPerScreen')}
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Translation Settings */}
        <div className="border-t border-zinc-900 pt-4 flex flex-col gap-4">
          <div className="flex items-center justify-between">
            <div className="flex flex-col">
              <label htmlFor="withTranslation" className="text-xs text-zinc-300 font-semibold cursor-pointer">
                {t('render.includeTranslation')}
              </label>
              <span className="text-[10px] text-zinc-500 font-mono">{t('render.includeTranslationDesc')}</span>
            </div>
            <input
              id="withTranslation"
              type="checkbox"
              disabled={disabled}
              checked={withTranslation}
              onChange={(e) => onWithTranslationChange(e.target.checked)}
              className="w-4 h-4 accent-emerald-500 rounded bg-zinc-900 border-zinc-800 focus:ring-emerald-500 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            />
          </div>

          {withTranslation && (
            <div className="flex flex-col gap-2">
              <label htmlFor="translationSource" className="text-xs text-zinc-500 font-mono">
                {t('render.translationSourceLabel')}
              </label>
              <select
                id="translationSource"
                disabled={disabled}
                value={translationSource}
                onChange={(e) => onTranslationSourceChange(e.target.value)}
                className="w-full bg-zinc-900 border border-zinc-800 text-zinc-200 px-3 py-2 rounded-xl focus:outline-none focus:border-zinc-700 transition-colors font-sans text-xs cursor-pointer"
              >
                <option value="sahih_international">{t('render.sahihInternational')}</option>
              </select>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
