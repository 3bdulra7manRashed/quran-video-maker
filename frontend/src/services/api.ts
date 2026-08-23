export interface Reciter {
  id: number;
  slug: string;
  name_arabic: string;
  name_english: string;
}

export interface Surah {
  number: number;
  name_arabic: string;
  name_english: string;
  verses_count: number;
}

export interface DatasetStatus {
  reciter: string;
  surah: number;
  glyphs: boolean;
  audio: boolean;
  timings: {
    mode: string;
    timedWords: number;
    totalWords: number;
  };
  renderable: boolean;
  approved_segments?: Array<{ order: number; arabic: string; translation: string; tafsir: string }>;
  translations?: {
    json_exists: boolean;
    table_populated: boolean;
    ready: boolean;
    error: string | null;
  };
}

export interface PrepareResponse {
  status: string;
  job_id?: string | number;
}

export interface UploadResponse {
  success: boolean;
  timedWords?: number;
  error?: string;
}

export interface RenderJobResponse {
  uuid: string;
  status: 'queued' | 'running' | 'completed' | 'failed' | 'cancelling' | 'cancelled';
  progress?: number;
  video?: {
    filename: string;
    url: string;
  };
  error?: string;
}

export interface RenderJobDetails {
  uuid: string;
  status: 'queued' | 'running' | 'completed' | 'failed' | 'cancelling' | 'cancelled';
  reciter: string;
  reciter_ar: string;
  surah: number;
  layout?: string;
  max_lines?: number;
  from_ayah: number | null;
  to_ayah: number | null;
  progress: number;
  created_at: string;
  error: string | null;
  filename?: string;
  url?: string;
  with_translation?: boolean;
  translation_source?: string | null;
}

export interface VideoFile {
  filename: string;
  reciter: string;
  reciter_en: string;
  surah: number;
  layout?: string;
  max_lines?: number;
  from_ayah: number | null;
  to_ayah: number | null;
  duration_seconds: number;
  duration_human: string;
  created_at: string;
  url: string;
}

export class ApiService {
  public baseUrl: string;

  constructor(baseUrl: string) {
    this.baseUrl = baseUrl.replace(/\/+$/, '');
  }

  async getReciters(): Promise<Reciter[]> {
    const res = await fetch(`${this.baseUrl}/api/reciters`);
    if (!res.ok) throw new Error('Failed to fetch reciters');
    return res.json();
  }

  async getSurahs(): Promise<Surah[]> {
    const res = await fetch(`${this.baseUrl}/api/surahs`);
    if (!res.ok) throw new Error('Failed to fetch surahs');
    return res.json();
  }

  async getDatasetStatus(reciter: string, surah: number): Promise<DatasetStatus> {
    const res = await fetch(`${this.baseUrl}/api/datasets/status?reciter=${reciter}&surah=${surah}`);
    if (!res.ok) throw new Error('Failed to fetch dataset status');
    return res.json();
  }

  async prepareDataset(reciter: string, surah: number): Promise<PrepareResponse> {
    const res = await fetch(`${this.baseUrl}/api/datasets/prepare`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ reciter, surah }),
    });
    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || 'Failed to prepare dataset');
    }
    return res.json();
  }

  async uploadAudio(reciter: string, surah: number, file: File): Promise<UploadResponse> {
    const formData = new FormData();
    formData.append('reciter', reciter);
    formData.append('surah', String(surah));
    formData.append('file', file);

    const res = await fetch(`${this.baseUrl}/api/datasets/audio`, {
      method: 'POST',
      body: formData,
    });

    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || 'Failed to upload audio');
    }
    return res.json();
  }

  async uploadTimings(reciter: string, file: File): Promise<UploadResponse> {
    const formData = new FormData();
    formData.append('reciter', reciter);
    formData.append('file', file);

    const res = await fetch(`${this.baseUrl}/api/datasets/timings`, {
      method: 'POST',
      body: formData,
    });

    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || 'Failed to upload timings');
    }
    return res.json();
  }

  async startRender(params: {
    reciter: string;
    surah: number;
    scope: 'full' | 'single' | 'range';
    ayah?: number;
    from?: number;
    to?: number;
    layout?: string;
    max_lines?: number;
    with_translation?: boolean;
    translation_source?: string;
    with_tafsir?: boolean;
    tafsir_source?: string;
    use_generated_content?: boolean;
    custom_json?: string;
  }): Promise<{ uuid: string; status: string }> {
    const res = await fetch(`${this.baseUrl}/api/renders`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(params),
    });
    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || 'Failed to start render');
    }
    return res.json();
  }

  async getRenderStatus(uuid: string): Promise<RenderJobResponse> {
    const res = await fetch(`${this.baseUrl}/api/renders/${uuid}`);
    if (!res.ok) throw new Error('Failed to fetch render status');
    return res.json();
  }

  async cancelRender(uuid: string): Promise<{ success: boolean; status: string }> {
    const res = await fetch(`${this.baseUrl}/api/renders/${uuid}/cancel`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
    });
    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || 'Failed to cancel render job');
    }
    return res.json();
  }

  async getAllRenders(): Promise<RenderJobDetails[]> {
    const res = await fetch(`${this.baseUrl}/api/renders`);
    if (!res.ok) throw new Error('Failed to fetch render jobs');
    return res.json();
  }

  async getVideos(): Promise<VideoFile[]> {
    const res = await fetch(`${this.baseUrl}/api/videos`);
    if (!res.ok) throw new Error('Failed to fetch videos');
    return res.json();
  }

  async deleteVideo(filename: string): Promise<{ success: boolean }> {
    const res = await fetch(`${this.baseUrl}/api/videos/${encodeURIComponent(filename)}`, {
      method: 'DELETE',
    });
    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.message || 'Failed to delete video');
    }
    return res.json();
  }

  async generatePrompt(params: {
    reciter: string;
    surah: number;
    from_ayah?: number | null;
    to_ayah?: number | null;
  }): Promise<{ prompt: string }> {
    const query = new URLSearchParams({
      reciter: params.reciter,
      surah: String(params.surah),
    });
    if (params.from_ayah) query.append('from_ayah', String(params.from_ayah));
    if (params.to_ayah) query.append('to_ayah', String(params.to_ayah));

    const res = await fetch(`${this.baseUrl}/api/datasets/generate-prompt?${query}`);
    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || 'Failed to generate prompt');
    }
    return res.json();
  }

  async generateMushafPrompt(params: {
    surah: number;
    from_ayah?: number | null;
    to_ayah?: number | null;
    start_page: number;
    start_line: number;
    markers: string;
  }): Promise<{ prompt: string }> {
    const res = await fetch(`${this.baseUrl}/api/datasets/generate-mushaf-prompt`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(params),
    });
    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || 'Failed to generate prompt');
    }
    return res.json();
  }

  async generateSegmentTimingPrompt(params: {
    surah: number;
    reciter: string;
    from_ayah?: number | null;
    to_ayah?: number | null;
    markers: string;
  }): Promise<{ prompt: string }> {
    const res = await fetch(`${this.baseUrl}/api/datasets/generate-segment-timing-prompt`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(params),
    });
    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || 'Failed to generate prompt');
    }
    return res.json();
  }

  async previewImport(params: {
    json: string;
    surah: number;
    from_ayah?: number | null;
    to_ayah?: number | null;
  }): Promise<{
    isValid: boolean;
    errors: string[];
    warnings: string[];
    segments?: Array<{ order: number; arabic: string; translation: string; tafsir: string }>;
    repairedJson?: string;
  }> {
    const res = await fetch(`${this.baseUrl}/api/datasets/preview-import`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(params),
    });
    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || 'Failed to validate JSON');
    }
    return res.json();
  }

  async approveImport(params: {
    json: string;
    reciter: string;
    surah: number;
    from_ayah?: number | null;
    to_ayah?: number | null;
    generator_type: string;
    generator_model?: string;
    generator_latency_ms?: number | null;
    prompt_version?: string;
    prompt_hash?: string;
  }): Promise<{ success: boolean; version: number }> {
    const res = await fetch(`${this.baseUrl}/api/datasets/approve-import`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(params),
    });
    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || 'Failed to save approved content');
    }
    return res.json();
  }
}

