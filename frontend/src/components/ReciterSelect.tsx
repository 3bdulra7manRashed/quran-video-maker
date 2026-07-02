'use client';

import { Reciter } from '@/services/api';
import { useT } from '@/hooks/useT';

interface ReciterSelectProps {
  reciters: Reciter[];
  selectedSlug: string;
  onSelect: (slug: string) => void;
  loading: boolean;
}

export default function ReciterSelect({ reciters, selectedSlug, onSelect, loading }: ReciterSelectProps) {
  const t = useT();

  return (
    <div className="flex flex-col gap-2">
      <label className="text-sm font-semibold text-zinc-400 flex items-center justify-between">
        <span>{t('render.reciterSelection')}</span>
      </label>

      <div className="relative">
        <select
          value={selectedSlug}
          onChange={(e) => onSelect(e.target.value)}
          disabled={loading}
          className="w-full bg-zinc-900 border border-zinc-800 text-zinc-200 ps-4 pe-8 py-3 rounded-xl appearance-none focus:outline-none focus:border-zinc-700 disabled:opacity-50 transition-all font-medium text-start"
        >
          <option value="" className="text-zinc-500">
            {t('render.selectReciterPlaceholder')}
          </option>
          {reciters.map((r) => (
            <option key={r.slug} value={r.slug}>
              {r.name_arabic} ({r.name_english})
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
