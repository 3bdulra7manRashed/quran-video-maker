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
  status: 'queued' | 'running' | 'completed' | 'failed';
  progress?: number;
  video?: {
    filename: string;
    url: string;
  };
  error?: string;
}

export interface RenderJobDetails {
  uuid: string;
  status: 'queued' | 'running' | 'completed' | 'failed';
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
}
