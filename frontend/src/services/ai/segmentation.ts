import { ApiService } from '../api';
import { AITaskParams, SegmentationVerifyResult } from './types';

export async function generateSegmentationPrompt(
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

  const res = await api.generatePrompt({
    reciter: params.reciterSlug,
    surah: params.surahNumber,
    from_ayah,
    to_ayah,
  });
  return res.prompt;
}

export async function verifySegmentationJson(
  api: ApiService,
  params: AITaskParams,
  json: string
): Promise<SegmentationVerifyResult> {
  let from_ayah: number | null = null;
  let to_ayah: number | null = null;
  if (params.scope === 'single') {
    from_ayah = params.ayahNumber;
    to_ayah = params.ayahNumber;
  } else if (params.scope === 'range') {
    from_ayah = params.fromAyah;
    to_ayah = params.toAyah;
  }

  return api.previewImport({
    json,
    surah: params.surahNumber,
    from_ayah,
    to_ayah,
  });
}

export async function approveSegmentationJson(
  api: ApiService,
  params: AITaskParams,
  json: string,
  generatorType: string,
  generatorModel: string,
  latencyMs?: number
): Promise<{ success: boolean; version: number }> {
  let from_ayah: number | null = null;
  let to_ayah: number | null = null;
  if (params.scope === 'single') {
    from_ayah = params.ayahNumber;
    to_ayah = params.ayahNumber;
  } else if (params.scope === 'range') {
    from_ayah = params.fromAyah;
    to_ayah = params.toAyah;
  }

  return api.approveImport({
    json,
    reciter: params.reciterSlug,
    surah: params.surahNumber,
    from_ayah,
    to_ayah,
    generator_type: generatorType,
    generator_model: generatorModel,
    generator_latency_ms: latencyMs,
  });
}
