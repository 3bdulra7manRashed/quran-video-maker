'use client';

import React, { useEffect, useState, useMemo } from 'react';
import { useApi } from '@/context/ApiContext';
import { RenderJobDetails } from '@/services/api';
import { useLanguage } from '@/hooks/useLanguage';
import { useT } from '@/hooks/useT';

type FilterTab = 'all' | 'running' | 'completed' | 'queued';

export default function JobsPage() {
  const { api } = useApi();
  const { language } = useLanguage();
  const isAr = language === 'ar';
  const t = useT();

  const [jobs, setJobs] = useState<RenderJobDetails[]>([]);
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [filterTab, setFilterTab] = useState<FilterTab>('all');

  // For inline video player modal
  const [activeVideo, setActiveVideo] = useState<{ filename: string; url: string } | null>(null);

  const inFlightRef = React.useRef(false);

  // Fetch all jobs
  async function loadJobs() {
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    try {
      const allJobs = await api.getAllRenders();
      setJobs(allJobs);
      setErrorMsg(null);
    } catch (err) {
      console.error('Failed to load render jobs:', err);
      setErrorMsg(t('jobs.failedToLoad'));
    } finally {
      inFlightRef.current = false;
      setLoading(false);
    }
  }

  // Cancel job request
  const handleCancel = async (uuid: string) => {
    try {
      await api.cancelRender(uuid);
      await loadJobs();
    } catch (err: any) {
      console.error('Failed to cancel job:', err);
      alert(err.message || t('jobs.failedToCancel'));
    }
  };

  useEffect(() => {
    loadJobs();
    
    // Poll jobs every 3 seconds to update progress/status
    const interval = setInterval(() => {
      loadJobs();
    }, 3000);

    return () => clearInterval(interval);
  }, []);

  const formatScope = (job: RenderJobDetails) => {
    if (job.from_ayah === null || job.to_ayah === null) {
      return isAr ? 'السورة كاملة' : 'Full Surah';
    }
    if (job.from_ayah === job.to_ayah) {
      return isAr ? `الآية ${job.from_ayah}` : `Ayah ${job.from_ayah}`;
    }
    return isAr ? `الآيات (${job.from_ayah} - ${job.to_ayah})` : `Ayahs (${job.from_ayah} - ${job.to_ayah})`;
  };

  // Metrics computation
  const metrics = useMemo(() => {
    const total = jobs.length;
    const running = jobs.filter((j) => j.status === 'running');
    const completed = jobs.filter((j) => j.status === 'completed');
    const queued = jobs.filter((j) => j.status === 'queued' || j.status === 'cancelling');
    const failed = jobs.filter((j) => j.status === 'failed');

    return {
      total,
      runningCount: running.length,
      completedCount: completed.length,
      queuedCount: queued.length,
      failedCount: failed.length,
      activeJob: running[0] || null,
    };
  }, [jobs]);

  // Filtered jobs list
  const filteredJobs = useMemo(() => {
    if (filterTab === 'all') return jobs;
    if (filterTab === 'running') return jobs.filter((j) => j.status === 'running');
    if (filterTab === 'completed') return jobs.filter((j) => j.status === 'completed');
    if (filterTab === 'queued') return jobs.filter((j) => j.status === 'queued' || j.status === 'cancelling');
    return jobs;
  }, [jobs, filterTab]);

  // Format elapsed and remaining time
  const formatTimeMetrics = (createdAt: string, progress: number) => {
    const elapsedMs = Math.max(0, Date.now() - new Date(createdAt).getTime());
    const elapsedSec = Math.floor(elapsedMs / 1000);
    const elapsedMin = Math.floor(elapsedSec / 60);
    const remSec = elapsedSec % 60;
    const elapsedStr = `${String(elapsedMin).padStart(2, '0')}:${String(remSec).padStart(2, '0')}`;

    if (progress > 0 && progress < 100) {
      const estimatedTotalSec = (elapsedSec / progress) * 100;
      const remainingSec = Math.max(0, Math.floor(estimatedTotalSec - elapsedSec));
      const remMin = Math.floor(remainingSec / 60);
      const remSecOnly = remainingSec % 60;
      const etaStr = `${String(remMin).padStart(2, '0')}:${String(remSecOnly).padStart(2, '0')}`;
      return { elapsedStr, etaStr };
    }

    return { elapsedStr, etaStr: '--:--' };
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Title & Hub Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-surface-variant/30 pb-4">
        <div>
          <h1 className="text-xl sm:text-2xl font-extrabold text-on-surface flex items-center gap-2">
            <span className="material-symbols-outlined text-primary text-[28px]">movie_creation</span>
            <span>{isAr ? 'مركز الرندرة وقائمة الانتظار' : 'Render & Export Hub'}</span>
          </h1>
          <p className="text-xs text-on-surface-variant mt-1">
            {isAr
              ? 'مراقبة تقدم توليد الفيديو، إدارة مهام الرندرة النشطة، ومعاينة وتنزيل المقاطع المكتملة'
              : 'Monitor video render progress, manage active queue, preview and download completed reels'}
          </p>
        </div>

        <button
          onClick={() => loadJobs()}
          disabled={loading}
          className="self-start sm:self-auto flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-semibold border border-surface-variant/40 transition-colors cursor-pointer"
        >
          <span className={`material-symbols-outlined text-[16px] text-primary ${loading ? 'animate-spin' : ''}`}>
            sync
          </span>
          <span>{isAr ? 'تحديث القائمة' : 'Refresh Queue'}</span>
        </button>
      </div>

      {/* Telemetry Summary Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {/* Total Jobs */}
        <div className="bg-surface-container rounded-xl p-3.5 border border-surface-variant/30 flex items-center justify-between shadow-sm">
          <div className="flex flex-col">
            <span className="text-[11px] text-on-surface-variant font-medium">{isAr ? 'إجمالي المهام' : 'Total Jobs'}</span>
            <span className="text-xl font-bold text-on-surface font-mono mt-0.5">{metrics.total}</span>
          </div>
          <div className="w-8 h-8 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary">
            <span className="material-symbols-outlined text-[20px]">dataset</span>
          </div>
        </div>

        {/* In Progress */}
        <div className="bg-surface-container rounded-xl p-3.5 border border-secondary/30 flex items-center justify-between shadow-sm">
          <div className="flex flex-col">
            <span className="text-[11px] text-on-surface-variant font-medium">{isAr ? 'قيد المعالجة' : 'Rendering'}</span>
            <span className="text-xl font-bold text-secondary font-mono mt-0.5 flex items-center gap-1.5">
              {metrics.runningCount > 0 && <span className="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>}
              <span>{metrics.runningCount}</span>
            </span>
          </div>
          <div className="w-8 h-8 rounded-lg bg-surface-container-lowest flex items-center justify-center text-secondary">
            <span className="material-symbols-outlined text-[20px] animate-spin">sync</span>
          </div>
        </div>

        {/* Completed */}
        <div className="bg-surface-container rounded-xl p-3.5 border border-surface-variant/30 flex items-center justify-between shadow-sm">
          <div className="flex flex-col">
            <span className="text-[11px] text-on-surface-variant font-medium">{isAr ? 'المكتملة' : 'Completed'}</span>
            <span className="text-xl font-bold text-on-surface font-mono mt-0.5">{metrics.completedCount}</span>
          </div>
          <div className="w-8 h-8 rounded-lg bg-surface-container-lowest flex items-center justify-center text-emerald-400">
            <span className="material-symbols-outlined text-[20px]">check_circle</span>
          </div>
        </div>

        {/* Queued / Pending */}
        <div className="bg-surface-container rounded-xl p-3.5 border border-surface-variant/30 flex items-center justify-between shadow-sm">
          <div className="flex flex-col">
            <span className="text-[11px] text-on-surface-variant font-medium">{isAr ? 'في الانتظار' : 'Queued'}</span>
            <span className="text-xl font-bold text-on-surface font-mono mt-0.5">{metrics.queuedCount}</span>
          </div>
          <div className="w-8 h-8 rounded-lg bg-surface-container-lowest flex items-center justify-center text-on-surface-variant">
            <span className="material-symbols-outlined text-[20px]">schedule</span>
          </div>
        </div>
      </div>

      {/* Filter Tabs Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-surface-variant/30 pb-2">
        <div className="flex items-center gap-1 bg-surface-container-lowest p-1 rounded-xl border border-surface-variant/30 text-xs">
          <button
            type="button"
            onClick={() => setFilterTab('all')}
            className={`px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 font-medium ${
              filterTab === 'all'
                ? 'bg-primary text-on-primary font-bold shadow-sm'
                : 'text-on-surface-variant hover:text-on-surface'
            }`}
          >
            <span>{isAr ? 'الكل' : 'All'}</span>
            <span className="opacity-80 font-mono">({metrics.total})</span>
          </button>

          <button
            type="button"
            onClick={() => setFilterTab('running')}
            className={`px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 font-medium ${
              filterTab === 'running'
                ? 'bg-secondary text-on-secondary font-bold shadow-sm'
                : 'text-on-surface-variant hover:text-on-surface'
            }`}
          >
            <span className="w-1.5 h-1.5 rounded-full bg-secondary"></span>
            <span>{isAr ? 'قيد المعالجة' : 'Rendering'}</span>
            <span className="opacity-80 font-mono">({metrics.runningCount})</span>
          </button>

          <button
            type="button"
            onClick={() => setFilterTab('completed')}
            className={`px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 font-medium ${
              filterTab === 'completed'
                ? 'bg-primary text-on-primary font-bold shadow-sm'
                : 'text-on-surface-variant hover:text-on-surface'
            }`}
          >
            <span>{isAr ? 'المكتملة' : 'Completed'}</span>
            <span className="opacity-80 font-mono">({metrics.completedCount})</span>
          </button>

          <button
            type="button"
            onClick={() => setFilterTab('queued')}
            className={`px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 font-medium ${
              filterTab === 'queued'
                ? 'bg-surface-container-high text-on-surface font-bold shadow-sm'
                : 'text-on-surface-variant hover:text-on-surface'
            }`}
          >
            <span>{isAr ? 'في الانتظار' : 'Queued'}</span>
            <span className="opacity-80 font-mono">({metrics.queuedCount})</span>
          </button>
        </div>
      </div>

      {errorMsg && (
        <div className="bg-red-500/10 text-red-400 border border-red-500/30 px-4 py-3 rounded-xl text-xs font-mono flex items-center justify-between">
          <span>{errorMsg}</span>
          <button 
            onClick={() => loadJobs()} 
            className="bg-surface-container-high hover:bg-surface-bright border border-surface-variant/40 px-2.5 py-1 rounded text-xs text-on-surface"
          >
            {t('common.retryConnection')}
          </button>
        </div>
      )}

      {loading && jobs.length === 0 ? (
        <div className="flex flex-col items-center justify-center min-h-[30vh]">
          <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
          <span className="text-xs text-on-surface-variant mt-3 font-mono">{t('jobs.loadingQueue')}</span>
        </div>
      ) : filteredJobs.length === 0 ? (
        <div className="border border-surface-variant/40 bg-surface-container rounded-xl p-12 text-center flex flex-col items-center justify-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-surface-container-lowest border border-surface-variant/30 flex items-center justify-center text-primary">
            <span className="material-symbols-outlined text-[24px]">movie_filter</span>
          </div>
          <h3 className="font-bold text-on-surface text-sm">
            {filterTab === 'all'
              ? (isAr ? 'لا توجد مهام رندرة حالياً' : 'No Render Jobs in Queue')
              : (isAr ? 'لا توجد مهام تطابق هذا التصنيف' : 'No jobs matching this filter')}
          </h3>
          <p className="text-xs text-on-surface-variant max-w-sm leading-relaxed">
            {isAr
              ? 'توجه إلى صفحة الإنتاج لاختيار سورة وقارئ وبدء عملية التصدير.'
              : 'Go to the studio production workbench to select a Surah and reciter to start rendering.'}
          </p>
        </div>
      ) : (
        <div className="flex flex-col gap-4">
          {filteredJobs.map((job) => {
            const isRunning = job.status === 'running';
            const isCompleted = job.status === 'completed';
            const isFailed = job.status === 'failed';
            const isCancelled = job.status === 'cancelled' || job.status === 'cancelling';
            const { elapsedStr, etaStr } = formatTimeMetrics(job.created_at, job.progress);

            return (
              <div
                key={job.uuid}
                className={`relative overflow-hidden rounded-xl bg-surface-container p-4 shadow-sm border transition-all ${
                  isRunning
                    ? 'border-primary/40 shadow-lg shadow-black/30'
                    : 'border-surface-variant/30 hover:border-surface-variant/60'
                }`}
              >
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  {/* Left: Thumbnail & Info */}
                  <div className="flex items-center gap-3.5 min-w-0">
                    {/* Thumbnail box */}
                    <div className="relative w-14 h-18 sm:w-16 sm:h-22 rounded-lg overflow-hidden flex-shrink-0 bg-surface-container-lowest border border-surface-variant/30 flex items-center justify-center">
                      {isRunning ? (
                        <div className="flex flex-col items-center justify-center gap-1">
                          <span className="material-symbols-outlined text-primary text-[22px] animate-spin">sync</span>
                          <span className="text-[10px] text-primary font-mono font-bold">{job.progress}%</span>
                        </div>
                      ) : isCompleted ? (
                        <div className="flex flex-col items-center justify-center text-secondary">
                          <span className="material-symbols-outlined text-[26px]">play_circle</span>
                        </div>
                      ) : isFailed ? (
                        <span className="material-symbols-outlined text-red-400 text-[24px]">error</span>
                      ) : (
                        <span className="material-symbols-outlined text-on-surface-variant/60 text-[24px]">schedule</span>
                      )}
                    </div>

                    {/* Metadata text */}
                    <div className="flex flex-col gap-1 min-w-0">
                      {/* Badges row */}
                      <div className="flex items-center gap-1.5 flex-wrap text-[10px]">
                        <span className="px-1.5 py-0.5 rounded bg-surface-container-lowest text-on-surface font-mono font-bold border border-surface-variant/30 uppercase">
                          {job.layout === 'youtube' ? '▶️ 16:9' : '📱 9:16'}
                        </span>

                        {/* Status Badge */}
                        {isRunning && (
                          <span className="px-2 py-0.5 rounded bg-secondary/15 text-secondary border border-secondary/30 font-bold flex items-center gap-1">
                            <span className="w-1.5 h-1.5 rounded-full bg-secondary animate-ping"></span>
                            <span>{isAr ? `جاري الرندرة ${job.progress}%` : `Rendering ${job.progress}%`}</span>
                          </span>
                        )}
                        {isCompleted && (
                          <span className="px-2 py-0.5 rounded bg-secondary/15 text-secondary border border-secondary/30 font-bold flex items-center gap-1">
                            <span className="material-symbols-outlined text-[12px]">check</span>
                            <span>{isAr ? 'جاهز للتنزيل' : 'Completed'}</span>
                          </span>
                        )}
                        {job.status === 'queued' && (
                          <span className="px-2 py-0.5 rounded bg-surface-container-lowest text-on-surface-variant border border-surface-variant/30 font-medium">
                            {isAr ? 'في الانتظار (Queued)' : 'In Queue'}
                          </span>
                        )}
                        {isCancelled && (
                          <span className="px-2 py-0.5 rounded bg-surface-container-lowest text-on-surface-variant border border-surface-variant/30">
                            {isAr ? 'ملغي' : 'Cancelled'}
                          </span>
                        )}
                        {isFailed && (
                          <span className="px-2 py-0.5 rounded bg-red-500/15 text-red-400 border border-red-500/30 font-bold">
                            {isAr ? 'فشل التصدير' : 'Failed'}
                          </span>
                        )}

                        {job.with_translation && (
                          <span className="px-1.5 py-0.5 rounded bg-surface-container-lowest text-on-surface-variant border border-surface-variant/20">
                            {isAr ? 'مع الترجمة' : 'Translation'}
                          </span>
                        )}
                      </div>

                      {/* Surah & Ayah Scope */}
                      <h3 className="text-sm font-bold text-on-surface flex items-center gap-2 truncate">
                        <span>{t('jobs.surahLabel', { number: job.surah })}</span>
                        <span className="text-xs font-normal text-on-surface-variant">
                          • {formatScope(job)}
                        </span>
                      </h3>

                      {/* Reciter name */}
                      <span className="text-xs text-on-surface-variant flex items-center gap-1">
                        <span className="material-symbols-outlined text-[14px] text-primary">person</span>
                        <span>{language === 'ar' ? job.reciter_ar : job.reciter}</span>
                      </span>
                    </div>
                  </div>

                  {/* Right: Metrics & Actions */}
                  <div className="flex sm:flex-col items-end justify-between sm:justify-center gap-2 flex-shrink-0">
                    {/* Time metrics */}
                    <div className="flex items-center gap-3 text-[11px] font-mono text-on-surface-variant">
                      <span>{isAr ? `المنقضي: ${elapsedStr}` : `Elapsed: ${elapsedStr}`}</span>
                      {isRunning && (
                        <span className="text-primary font-bold">{isAr ? `المتبقي: ~${etaStr}` : `ETA: ~${etaStr}`}</span>
                      )}
                    </div>

                    {/* Action buttons */}
                    <div className="flex items-center gap-1.5">
                      {isRunning && (
                        <button
                          type="button"
                          onClick={() => handleCancel(job.uuid)}
                          className="px-2.5 py-1.5 rounded-lg bg-red-500/15 hover:bg-red-500/25 text-red-400 border border-red-500/30 text-xs font-semibold flex items-center gap-1 transition-colors cursor-pointer"
                        >
                          <span className="material-symbols-outlined text-[14px]">close</span>
                          <span>{isAr ? 'إلغاء' : 'Cancel'}</span>
                        </button>
                      )}

                      {isCompleted && job.url && job.filename && (
                        <>
                          <button
                            type="button"
                            onClick={() => setActiveVideo({ filename: job.filename!, url: `${api.baseUrl}${job.url}` })}
                            className="px-3 py-1.5 rounded-lg bg-surface-container-high hover:bg-surface-bright text-on-surface text-xs font-semibold border border-surface-variant/40 flex items-center gap-1 transition-colors cursor-pointer"
                          >
                            <span className="material-symbols-outlined text-[16px] text-primary">play_arrow</span>
                            <span>{isAr ? 'معاينة' : 'Preview'}</span>
                          </button>

                          <a
                            href={`${api.baseUrl}${job.url}`}
                            download={job.filename}
                            className="px-3 py-1.5 rounded-lg bg-primary hover:bg-tertiary text-on-primary text-xs font-bold shadow-[0_0_10px_rgba(242,202,80,0.2)] flex items-center gap-1 transition-all"
                          >
                            <span className="material-symbols-outlined text-[16px]">download</span>
                            <span>{isAr ? 'تنزيل MP4' : 'Download'}</span>
                          </a>
                        </>
                      )}
                    </div>
                  </div>
                </div>

                {/* Progress bar for running jobs */}
                {isRunning && (
                  <div className="flex flex-col gap-1.5 pt-3 border-t border-surface-variant/20 mt-3">
                    <div className="w-full bg-surface-container-lowest h-2 rounded-full overflow-hidden p-0.5 border border-surface-variant/30">
                      <div
                        className="bg-gradient-to-l from-primary-container via-primary to-secondary h-full rounded-full shadow-[0_0_10px_rgba(242,202,80,0.6)] transition-all duration-300"
                        style={{ width: `${Math.max(4, job.progress)}%` }}
                      ></div>
                    </div>
                    <div className="flex justify-between items-center text-[10px] text-on-surface-variant font-mono">
                      <span className="flex items-center gap-1">
                        <span className="material-symbols-outlined text-[12px] text-secondary">memory</span>
                        <span>{isAr ? 'محرك الرندرة: FFmpeg Hardware' : 'Engine: FFmpeg Hardware'}</span>
                      </span>
                      <span className="text-primary font-bold">{job.progress}%</span>
                    </div>
                  </div>
                )}

                {/* Error Banner */}
                {isFailed && job.error && (
                  <div className="mt-3 p-2.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-mono break-words">
                    <strong>{isAr ? 'سبب الخطأ:' : 'Error:'}</strong> {job.error}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* Video Modal Player */}
      {activeVideo && (
        <div className="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-surface-container border border-surface-variant/40 max-w-4xl w-full rounded-2xl overflow-hidden shadow-2xl relative flex flex-col">
            <div className="px-5 py-3.5 border-b border-surface-variant/30 flex items-center justify-between">
              <span className="font-bold text-on-surface truncate font-mono text-xs flex items-center gap-2">
                <span className="material-symbols-outlined text-primary text-[18px]">movie</span>
                <span>{activeVideo.filename}</span>
              </span>
              <button
                onClick={() => setActiveVideo(null)}
                className="text-on-surface-variant hover:text-on-surface p-1 rounded-lg hover:bg-surface-container-high transition-colors cursor-pointer"
              >
                <span className="material-symbols-outlined text-[20px]">close</span>
              </button>
            </div>
            <div className="bg-black aspect-video flex items-center justify-center p-2">
              <video
                src={activeVideo.url}
                controls
                autoPlay
                className="w-full h-full rounded-lg object-contain"
              />
            </div>
            <div className="px-5 py-3 border-t border-surface-variant/30 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setActiveVideo(null)}
                className="px-4 py-2 rounded-xl bg-surface-container-high hover:bg-surface-bright text-on-surface text-xs font-semibold border border-surface-variant/40 cursor-pointer"
              >
                {isAr ? 'إغلاق' : 'Close'}
              </button>
              <a
                href={activeVideo.url}
                download={activeVideo.filename}
                className="bg-primary hover:bg-tertiary text-on-primary font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-[0_0_12px_rgba(242,202,80,0.3)] transition-all"
              >
                <span className="material-symbols-outlined text-[16px]">download</span>
                <span>{isAr ? 'تنزيل الفيديو MP4' : 'Download Video MP4'}</span>
              </a>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
