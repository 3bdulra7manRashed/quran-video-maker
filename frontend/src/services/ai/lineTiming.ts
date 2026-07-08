import { ApiService } from '../api';
import { AITaskParams } from './types';

export async function generateLineTimingPrompt(
  api: ApiService,
  params: AITaskParams
): Promise<string> {
  let from_ayah: number | null = null;
  let to_ayah: number | null = null;
  if (params.scope === 'single') {
    from_ayah = params.ayahNumber;
    to_ayah = params.ayahNumber;
  } else if (params.scope === 'range') {
    from_ayah = params.fromAyah;
    to_ayah = params.toAyah;
  }

  if (params.startPage === undefined || params.startLine === undefined) {
    throw new Error('Start Page and Start Line are required.');
  }

  if (!params.markers) {
    throw new Error('Adobe Audition markers are required.');
  }

  const res = await api.generateMushafPrompt({
    surah: params.surahNumber,
    from_ayah,
    to_ayah,
    start_page: params.startPage,
    start_line: params.startLine,
    markers: params.markers,
  });
  return res.prompt;
}

export async function importLineTimingJson(
  api: ApiService,
  params: AITaskParams,
  json: string
): Promise<{ success: boolean; timedLines?: number }> {
  // Convert JSON string to a File Blob
  const file = new File([json], 'mushaf_lines_timings.json', { type: 'application/json' });
  return api.uploadTimings(params.reciterSlug, file);
}
