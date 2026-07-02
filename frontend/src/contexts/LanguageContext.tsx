'use client';

import React, { createContext, useState, useEffect } from 'react';
import { Language } from '../i18n/i18n';

export interface LanguageContextType {
  language: Language;
  direction: 'rtl' | 'ltr';
  setLanguage: (language: Language) => void;
}

export const LanguageContext = createContext<LanguageContextType | undefined>(undefined);

export function LanguageProvider({ children }: { children: React.ReactNode }) {
  const [language, setLanguageState] = useState<Language>('ar');

  // Load language preference from localStorage on mount
  useEffect(() => {
    const saved = localStorage.getItem('qvm_language');
    if (saved === 'ar' || saved === 'en') {
      setLanguageState(saved);
    }
  }, []);

  const setLanguage = (lang: Language) => {
    setLanguageState(lang);
    localStorage.setItem('qvm_language', lang);
  };

  const direction = language === 'ar' ? 'rtl' : 'ltr';

  // Note: Phase 7 states "Default server-rendered state: dir='rtl' lang='ar'. Server defaults are sufficient. Avoid depending on useEffect / document.documentElement.dir unless future language switching genuinely requires it."
  // However, we want direction to automatically adapt on language change. In case we switch languages, setting dir/lang on documentElement is a clean, non-intrusive way to do this without server hydration mismatch.
  useEffect(() => {
    document.documentElement.dir = direction;
    document.documentElement.lang = language;
  }, [language, direction]);

  return (
    <LanguageContext.Provider value={{ language, direction, setLanguage }}>
      {children}
    </LanguageContext.Provider>
  );
}
