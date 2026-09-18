'use client';

import React, { useState, useEffect, useRef } from 'react';
import { Reciter } from '@/services/api';
import { useLanguage } from '@/hooks/useLanguage';

interface ReciterSelectProps {
  reciters: Reciter[];
  selectedSlug: string;
  onSelect: (slug: string) => void;
  loading: boolean;
}

export default function ReciterSelect({ reciters, selectedSlug, onSelect, loading }: ReciterSelectProps) {
  const { language } = useLanguage();
  const isAr = language === 'ar';

  const [isOpen, setIsOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const containerRef = useRef<HTMLDivElement>(null);

  // Sync active selection with search text when selection changes
  useEffect(() => {
    const selected = reciters.find((r) => r.slug === selectedSlug);
    if (selected) {
      setSearchQuery(isAr ? `${selected.name_arabic} (${selected.name_english})` : `${selected.name_english} (${selected.name_arabic})`);
    } else {
      setSearchQuery('');
    }
  }, [selectedSlug, reciters, isAr]);

  // Close dropdown on click outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
        // Reset query text to current selection
        const selected = reciters.find((r) => r.slug === selectedSlug);
        if (selected) {
          setSearchQuery(isAr ? `${selected.name_arabic} (${selected.name_english})` : `${selected.name_english} (${selected.name_arabic})`);
        } else {
          setSearchQuery('');
        }
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [selectedSlug, reciters, isAr]);

  const filteredReciters = reciters.filter((r) =>
    r.name_english.toLowerCase().includes(searchQuery.toLowerCase()) ||
    r.name_arabic.includes(searchQuery)
  );

  return (
    <div className="flex flex-col gap-1.5" ref={containerRef}>
      <label className="text-[11px] font-semibold text-on-surface-variant flex items-center justify-between">
        <span>{isAr ? 'القارئ (المصدر الصوتي والتوقيت)' : 'Reciter (Audio & Timings Source)'}</span>
        {selectedSlug && (
          <span className="text-[10px] text-primary font-mono font-medium">✓ {isAr ? 'محدد' : 'Selected'}</span>
        )}
      </label>
      
      <div className="relative">
        <input
          type="text"
          disabled={loading}
          value={searchQuery}
          onChange={(e) => {
            setSearchQuery(e.target.value);
            setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          placeholder={isAr ? 'ابحث عن القارئ أو اختر من القائمة...' : 'Search or choose reciter...'}
          className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface placeholder:text-on-surface-variant/40 px-3 py-2 rounded-xl text-xs focus:outline-none focus:border-primary disabled:opacity-40 font-medium transition-colors"
        />

        <div className="absolute inset-y-0 end-3 flex items-center pointer-events-none text-on-surface-variant/60 text-xs">
          <span className="material-symbols-outlined text-[18px]">arrow_drop_down</span>
        </div>

        {isOpen && !loading && (
          <div className="absolute z-50 w-full mt-1.5 bg-surface-container-low border border-surface-variant/40 rounded-xl shadow-2xl max-h-60 overflow-y-auto backdrop-blur-xl">
            {filteredReciters.length === 0 ? (
              <div className="px-3 py-3 text-xs text-on-surface-variant text-center">
                {isAr ? 'لم يتم العثور على قراء مطابقين.' : 'No matching reciters found.'}
              </div>
            ) : (
              filteredReciters.map((r) => (
                <button
                  key={r.slug}
                  type="button"
                  onClick={() => {
                    onSelect(r.slug);
                    setSearchQuery(isAr ? `${r.name_arabic} (${r.name_english})` : `${r.name_english} (${r.name_arabic})`);
                    setIsOpen(false);
                  }}
                  className={`w-full text-start px-3.5 py-2.5 text-xs font-medium transition-colors flex items-center justify-between border-b border-surface-variant/15 last:border-b-0 ${
                    selectedSlug === r.slug
                      ? 'bg-primary/15 text-primary font-bold'
                      : 'text-on-surface hover:bg-surface-container-high'
                  }`}
                >
                  <span className="font-arabic">{r.name_arabic}</span>
                  <span className="text-[10px] text-on-surface-variant font-mono opacity-80">{r.name_english}</span>
                </button>
              ))
            )}
          </div>
        )}
      </div>
    </div>
  );
}
