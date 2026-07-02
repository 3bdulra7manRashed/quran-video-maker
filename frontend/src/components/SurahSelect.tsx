'use client';

import { Surah } from '@/services/api';
import { useT } from '@/hooks/useT';

interface SurahSelectProps {
  surahs: Surah[];
  selectedNumber: number | '';
  onSelect: (number: number) => void;
  loading: boolean;
}

export default function SurahSelect({ surahs, selectedNumber, onSelect, loading }: SurahSelectProps) {
  const t = useT();

  return (
    <div className="flex flex-col gap-2">
      <label className="text-sm font-semibold text-zinc-400 flex items-center justify-between">
        <span>{t('render.surahSelection')}</span>
      </label>

      <div className="relative">
        <select
          value={selectedNumber}
          onChange={(e) => onSelect(Number(e.target.value))}
          disabled={loading}
          className="w-full bg-zinc-900 border border-zinc-800 text-zinc-200 ps-4 pe-8 py-3 rounded-xl appearance-none focus:outline-none focus:border-zinc-700 disabled:opacity-50 transition-all font-medium text-start"
        >
          <option value="" className="text-zinc-500">
            {t('render.selectSurahPlaceholder')}
          </option>
          {surahs.map((s) => (
            <option key={s.number} value={s.number}>
              {s.number}. {s.name_arabic} ({s.name_english})
            </option>
          ))}
        </select>
        <div className="absolute inset-y-0 end-3 flex items-center pointer-events-none text-zinc-500">
          ▼
        </div>
      </div>
    </div>
  );
}
