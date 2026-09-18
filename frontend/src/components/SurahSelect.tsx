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
      <label className="text-[11px] font-semibold text-on-surface-variant flex items-center justify-between">
        <span>{isAr ? 'اختر السورة القرآنية' : 'Choose Quran Surah'}</span>
        {selectedNumber !== '' && (
          <span className="text-[10px] text-primary font-mono font-medium">✓ {isAr ? 'محددة' : 'Selected'}</span>
        )}
      </label>

      <div className="relative">
        <select
          value={selectedNumber}
          onChange={(e) => onSelect(Number(e.target.value))}
          disabled={loading}
          style={{ backgroundColor: '#18181b', color: '#ffffff' }}
          className="w-full bg-zinc-900 border border-zinc-700/60 text-white ps-3 pe-8 py-2 rounded-xl appearance-none focus:outline-none focus:border-amber-500 disabled:opacity-40 font-medium text-xs cursor-pointer transition-colors"
        >
          <option
            value=""
            style={{ backgroundColor: '#18181b', color: '#a1a1aa' }}
            className="bg-zinc-900 text-zinc-400"
          >
            {t('render.selectSurahPlaceholder')}
          </option>
          {surahs.map((s) => (
            <option
              key={s.number}
              value={s.number}
              style={{ backgroundColor: '#18181b', color: '#ffffff' }}
              className="bg-zinc-900 text-white hover:bg-amber-500/10 hover:text-amber-500 checked:bg-amber-500/20 checked:text-amber-400"
            >
              {s.number}. {s.name_arabic} ({s.name_english})
            </option>
          ))}
        </select>
        <div className="absolute inset-y-0 end-2.5 flex items-center pointer-events-none text-zinc-400 text-xs">
          <span className="material-symbols-outlined text-[18px]">arrow_drop_down</span>
        </div>
      </div>
    </div>
  );
}
