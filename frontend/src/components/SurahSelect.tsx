'use client';

import { Surah } from '@/services/api';

interface SurahSelectProps {
  surahs: Surah[];
  selectedNumber: number | '';
  onSelect: (number: number) => void;
  loading: boolean;
}

export default function SurahSelect({ surahs, selectedNumber, onSelect, loading }: SurahSelectProps) {
  return (
    <div className="flex flex-col gap-2">
      <label className="text-sm font-semibold text-zinc-400 flex items-center justify-between">
        <span>Surah Selection</span>
        <span className="text-xs text-zinc-500 font-normal">اختيار السورة</span>
      </label>

      <div className="relative">
        <select
          value={selectedNumber}
          onChange={(e) => onSelect(Number(e.target.value))}
          disabled={loading}
          className="w-full bg-zinc-900 border border-zinc-800 text-zinc-200 px-4 py-3 rounded-xl appearance-none focus:outline-none focus:border-zinc-700 disabled:opacity-50 transition-all font-medium text-right dir-rtl"
        >
          <option value="" className="text-zinc-500">
            [ اختر السورة / Select Surah ]
          </option>
          {surahs.map((s) => (
            <option key={s.number} value={s.number}>
              {s.number}. {s.name_arabic} ({s.name_english})
            </option>
          ))}
        </select>
        <div className="absolute inset-y-0 left-3 flex items-center pointer-events-none text-zinc-500">
          ▼
        </div>
      </div>
    </div>
  );
}
