export interface LayoutConfig {
  id: string;
  nameEn: string;
  nameAr: string;
  descriptionEn: string;
  descriptionAr: string;
  renderer: string;
  timingType: 'segment' | 'line';
  translationSupport: boolean;
  tafsirSupport: boolean;
  maxLinesSupport: boolean;
  availableAiTasks: string[];
}

export const LAYOUTS: Record<string, LayoutConfig> = {
  reels: {
    id: 'reels',
    nameEn: '📱 Reels',
    nameAr: '📱 ريلز',
    descriptionEn: 'Short vertical videos optimized for social platforms.',
    descriptionAr: 'فيديوهات رأسية قصيرة مخصصة لمنصات التواصل الاجتماعي.',
    renderer: 'reels',
    timingType: 'segment',
    translationSupport: true,
    tafsirSupport: true,
    maxLinesSupport: false,
    availableAiTasks: ['segmentation', 'segment_timing'],
  },
  youtube: {
    id: 'youtube',
    nameEn: '▶️ YouTube',
    nameAr: '▶️ يوتيوب',
    descriptionEn: 'Full Mushaf pages and long-form recitations.',
    descriptionAr: 'عرض صفحة المصحف كاملة والتلاوات الطويلة.',
    renderer: 'youtube',
    timingType: 'line',
    translationSupport: true,
    tafsirSupport: true,
    maxLinesSupport: true,
    availableAiTasks: ['line_timing'],
  },
};
