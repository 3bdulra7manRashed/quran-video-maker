export interface AITask {
  id: string;
  titleEn: string;
  titleAr: string;
  descriptionEn: string;
  descriptionAr: string;
  icon: string;
  promptType: 'segmentation' | 'segment_timing' | 'line_timing';
  requiresMarkers: boolean;
  requiresMushafLineInfo: boolean;
}

export const AI_TASKS: Record<string, AITask> = {
  segmentation: {
    id: 'segmentation',
    titleEn: 'Generate Segments',
    titleAr: 'توليد المقاطع',
    descriptionEn: 'Split Quranic verses into optimal visual segments for vertical screens.',
    descriptionAr: 'تقسيم الآيات القرآنية إلى مقاطع بصرية مثالية للشاشات الرأسية.',
    icon: '✂️',
    promptType: 'segmentation',
    requiresMarkers: false,
    requiresMushafLineInfo: false,
  },
  segment_timing: {
    id: 'segment_timing',
    titleEn: 'Generate Segment Timings',
    titleAr: 'توليد توقيت المقاطع',
    descriptionEn: 'Map Adobe Audition marker timestamps to approved visual segments.',
    descriptionAr: 'ربط العلامات الزمنية من Adobe Audition بالمقاطع البصرية المعتمدة.',
    icon: '⏱️',
    promptType: 'segment_timing',
    requiresMarkers: true,
    requiresMushafLineInfo: false,
  },
  line_timing: {
    id: 'line_timing',
    titleEn: 'Generate Line Timings',
    titleAr: 'توليد توقيت السطور',
    descriptionEn: 'Map Adobe Audition marker timestamps to sequential Mushaf page and line coordinates.',
    descriptionAr: 'ربط العلامات الزمنية من Adobe Audition بأرقام الصفحات والسطور المتتالية للمصحف.',
    icon: '📖',
    promptType: 'line_timing',
    requiresMarkers: true,
    requiresMushafLineInfo: true,
  },
};
