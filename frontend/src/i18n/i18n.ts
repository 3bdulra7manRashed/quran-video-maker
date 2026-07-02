import commonAr from '../locales/ar/common.json';
import jobsAr from '../locales/ar/jobs.json';
import renderAr from '../locales/ar/render.json';
import settingsAr from '../locales/ar/settings.json';

import commonEn from '../locales/en/common.json';
import jobsEn from '../locales/en/jobs.json';
import renderEn from '../locales/en/render.json';
import settingsEn from '../locales/en/settings.json';

export type Language = 'ar' | 'en';

type CommonKeys =
  | 'appName'
  | 'mvp'
  | 'loading'
  | 'connectingToApi'
  | 'retryConnection'
  | 'errorConnectBackend'
  | 'nav.console'
  | 'nav.jobs'
  | 'nav.videos'
  | 'status.completed'
  | 'status.failed'
  | 'status.running'
  | 'status.cancelling'
  | 'status.cancelled'
  | 'status.queued'
  | 'cancel'
  | 'delete'
  | 'download'
  | 'play'
  | 'close'
  | 'clear'
  | 'enabled'
  | 'disabled'
  | 'videos.pageTitle'
  | 'videos.pageDescription'
  | 'videos.deleteSuccess'
  | 'videos.failedToLoad'
  | 'videos.searchPlaceholder'
  | 'videos.filterReciter'
  | 'videos.filterScope'
  | 'videos.filterSort'
  | 'videos.allReciters'
  | 'videos.allScopes'
  | 'videos.fullSurah'
  | 'videos.singleAyah'
  | 'videos.ayahRange'
  | 'videos.sortNewest'
  | 'videos.sortOldest'
  | 'videos.sortSurah'
  | 'videos.sortReciter'
  | 'videos.sortDuration'
  | 'videos.loadingVideos'
  | 'videos.noMatching'
  | 'videos.noMatchingDescription'
  | 'videos.downloadVideoFile'
  | 'videos.deleteVideo'
  | 'videos.surah'
  | 'videos.reciter'
  | 'videos.scope'
  | 'videos.duration'
  | 'videos.deleteWarning'
  | 'videos.deleting'
  | 'videos.deleteFailed'
  | 'videos.formatFullSurah'
  | 'videos.formatAyah'
  | 'videos.formatAyahs';

type JobsKeys =
  | 'pageTitle'
  | 'pageDescription'
  | 'loadingQueue'
  | 'noJobs'
  | 'noJobsDescription'
  | 'failedToLoad'
  | 'scope'
  | 'progress'
  | 'error'
  | 'cancelRender'
  | 'cancellingButton'
  | 'openVideo'
  | 'retry'
  | 'retryComingSoon'
  | 'renderCancelledByUser'
  | 'downloadVideoFile'
  | 'translation'
  | 'source'
  | 'surahLabel'
  | 'reciterLabel'
  | 'fullSurah'
  | 'ayahLabel'
  | 'ayahsRange'
  | 'failedToCancel'
  | 'youtube'
  | 'reels';

type RenderKeys =
  | 'pageTitle'
  | 'pageDescription'
  | 'targetSelection'
  | 'reciterSelection'
  | 'surahSelection'
  | 'selectReciterPlaceholder'
  | 'selectSurahPlaceholder'
  | 'renderOptions'
  | 'scopeFull'
  | 'scopeSingle'
  | 'scopeRange'
  | 'ayahNumberLabel'
  | 'fromLabel'
  | 'toLabel'
  | 'videoLayout'
  | 'layoutReels'
  | 'layoutYoutube'
  | 'maxLinesLabel'
  | 'oneLinePerScreen'
  | 'twoLinesPerScreen'
  | 'includeTranslation'
  | 'includeTranslationDesc'
  | 'translationSourceLabel'
  | 'sahihInternational'
  | 'generateVideo'
  | 'queuingJob'
  | 'generateButton'
  | 'generateFailed'
  | 'datasetNotRenderable'
  | 'datasetStatus'
  | 'glyphs'
  | 'audio'
  | 'timings'
  | 'readyToRender'
  | 'ready'
  | 'notReady'
  | 'timingFull'
  | 'timingPartial'
  | 'timingFallback'
  | 'timingNone'
  | 'datasetActions'
  | 'prepareDataset'
  | 'preparing'
  | 'refreshing'
  | 'uploadAudio'
  | 'uploadingAudio'
  | 'uploadTimings'
  | 'uploadingTimings'
  | 'prepareSuccess'
  | 'prepareFailed'
  | 'audioUploadSuccess'
  | 'audioUploadFailed'
  | 'timingsUploadSuccess'
  | 'timingsUploadFailed'
  | 'loadingStatus'
  | 'selectPrompt';

type SettingsKeys = 'apiUrl' | 'language' | 'arabic' | 'english';

export type TranslationKey =
  | `common.${CommonKeys}`
  | `jobs.${JobsKeys}`
  | `render.${RenderKeys}`
  | `settings.${SettingsKeys}`;

const translations: Record<Language, Record<string, any>> = {
  ar: {
    common: commonAr,
    jobs: jobsAr,
    render: renderAr,
    settings: settingsAr,
  },
  en: {
    common: commonEn,
    jobs: jobsEn,
    render: renderEn,
    settings: settingsEn,
  },
};

function getNestedValue(obj: any, path: string): string | undefined {
  if (!obj || typeof obj !== 'object') return undefined;
  const parts = path.split('.');
  let current = obj;
  for (const part of parts) {
    if (current && typeof current === 'object' && part in current) {
      current = current[part];
    } else {
      return undefined;
    }
  }
  return typeof current === 'string' ? current : undefined;
}

export function getTranslation(
  key: TranslationKey,
  lang: Language,
  variables?: Record<string, string | number>
): string {
  const parts = key.split('.');
  const namespace = parts[0];
  const restKey = parts.slice(1).join('.');

  let text = getNestedValue(translations[lang]?.[namespace], restKey);

  if (text === undefined && lang !== 'en') {
    // English fallback
    text = getNestedValue(translations['en']?.[namespace], restKey);
  }

  if (text === undefined) {
    if (process.env.NODE_ENV === 'development') {
      console.warn(`[i18n] Missing translation: ${key}`);
    }
    return key;
  }

  if (variables) {
    Object.entries(variables).forEach(([k, v]) => {
      text = text!.replace(new RegExp(`{{\\s*${k}\\s*}}`, 'g'), String(v));
    });
  }

  return text;
}
