'use client';

import React from 'react';

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
}: RenderOptionsProps) {
  return (
    <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 flex flex-col gap-5 shadow-2xl">
      <div className="flex items-center justify-between border-b border-zinc-900 pb-3">
        <h3 className="font-semibold text-zinc-200">Render Options</h3>
        <span className="text-xs text-zinc-500 font-mono">خيارات الرندر</span>
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
            Full Surah
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
            Single Ayah
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
            Ayah Range
          </button>
        </div>

        {/* Conditional Inputs */}
        {scope === 'single' && (
          <div className="flex flex-col gap-2">
            <label className="text-xs text-zinc-500 font-mono">Ayah Number (1 - {maxAyahs}):</label>
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
              <label className="text-xs text-zinc-500 font-mono">From:</label>
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
              <label className="text-xs text-zinc-500 font-mono">To (max {maxAyahs}):</label>
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
            <label className="text-xs text-zinc-500 font-mono">Video Layout (الأبعاد والتخطيط):</label>
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
                Reels (Vertical 1080x1920)
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
                YouTube (Landscape 1920x1080)
              </button>
            </div>
          </div>

          {layout === 'youtube' && (
            <div className="flex flex-col gap-2">
              <label className="text-xs text-zinc-500 font-mono">Max Mushaf Lines per Screen (أسطر المصحف):</label>
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
                  1 Line / screen
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
                  2 Lines / screen
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
