'use client';

import { useLanguage } from './useLanguage';
import { getTranslation, TranslationKey } from '../i18n/i18n';

export function useT() {
  const { language } = useLanguage();
  
  return (key: TranslationKey, variables?: Record<string, string | number>): string => {
    return getTranslation(key, language, variables);
  };
}
