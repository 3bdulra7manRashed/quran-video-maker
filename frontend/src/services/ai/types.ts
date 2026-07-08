export interface AITaskParams {
  surahNumber: number;
  reciterSlug: string;
  scope: 'full' | 'single' | 'range';
  ayahNumber: number;
  fromAyah: number;
  toAyah: number;
  markers?: string;
  startPage?: number;
  startLine?: number;
}

export interface AITaskExecuteResult {
  success: boolean;
  prompt?: string;
  error?: string;
}

export interface SegmentationVerifyResult {
  isValid: boolean;
  errors: string[];
  warnings: string[];
  segments?: Array<{ order: number; arabic: string; translation: string; tafsir: string }>;
  repairedJson?: string;
}
