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
      <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
        {isAr ? 'اختر القارئ' : 'Choose Reciter'}
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
          placeholder={isAr ? 'ابحث عن القارئ...' : 'Search reciter...'}
          className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl text-sm focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 disabled:opacity-50 font-medium"
        />

        <div className="absolute inset-y-0 end-3 flex items-center pointer-events-none text-slate-400 text-xs">
          ▼
        </div>

        {isOpen && !loading && (
          <div className="absolute z-50 w-full mt-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-lg max-h-60 overflow-y-auto">
            {filteredReciters.length === 0 ? (
              <div className="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                {isAr ? 'لم يتم العثور على قراء.' : 'No reciter found.'}
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
                  className={`w-full text-start px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 ${
                    selectedSlug === r.slug ? 'bg-slate-50 dark:bg-slate-800/50 font-semibold' : ''
                  }`}
                >
                  {isAr ? `${r.name_arabic} (${r.name_english})` : `${r.name_english} (${r.name_arabic})`}
                </button>
              ))
            )}
          </div>
        )}
      </div>
    </div>
  );
}
