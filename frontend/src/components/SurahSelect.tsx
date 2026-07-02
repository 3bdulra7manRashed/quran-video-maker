'use client';

import { Surah } from '@/services/api';
import { useT } from '@/hooks/useT';
import { useLanguage } from '@/hooks/useLanguage';

interface SurahSelectProps {
  surahs: Surah[];
  selectedNumber: number | '';
  onSelect: (number: number) => void;
  loading: boolean;
}

export default function SurahSelect({ surahs, selectedNumber, onSelect, loading }: SurahSelectProps) {
  const t = useT();
  const { language } = useLanguage();
  const isAr = language === 'ar';

  return (
    <div className="flex flex-col gap-1.5">
      <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
        {isAr ? 'اختر السورة' : 'Choose Surah'}
      </label>

      <div className="relative">
        <select
          value={selectedNumber}
          onChange={(e) => onSelect(Number(e.target.value))}
          disabled={loading}
          className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 ps-3 pe-8 py-2 rounded-xl appearance-none focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 disabled:opacity-50 font-medium text-sm cursor-pointer"
        >
          <option value="" className="text-slate-500 dark:text-slate-400">
            {t('render.selectSurahPlaceholder')}
          </option>
          {surahs.map((s) => (
            <option key={s.number} value={s.number}>
              {s.number}. {s.name_english} ({s.name_arabic})
            </option>
          ))}
        </select>
        <div className="absolute inset-y-0 end-3 flex items-center pointer-events-none text-slate-400 text-xs">
          ▼
        </div>
      </div>
    </div>
  );
}
