import { ApiService } from '../api';
import { AITaskParams } from './types';

export async function generateSegmentTimingPrompt(
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

  if (!params.markers) {
    throw new Error('Adobe Audition markers are required.');
  }

  const res = await api.generateSegmentTimingPrompt({
    surah: params.surahNumber,
    reciter: params.reciterSlug,
    from_ayah,
    to_ayah,
    markers: params.markers,
  });
  return res.prompt;
}

export async function importSegmentTimingJson(
  api: ApiService,
  params: AITaskParams,
  json: string
): Promise<{ success: boolean; timedWords?: number }> {
  const file = new File([json], 'segment_timings.json', { type: 'application/json' });
  return api.uploadTimings(params.reciterSlug, file);
}
