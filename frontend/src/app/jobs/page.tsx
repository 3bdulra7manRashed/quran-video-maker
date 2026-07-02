'use client';

import React, { useEffect, useState } from 'react';
import { useApi } from '@/context/ApiContext';
import { RenderJobDetails } from '@/services/api';
import { useLanguage } from '@/hooks/useLanguage';
import { useT } from '@/hooks/useT';

export default function JobsPage() {
  const { api } = useApi();
  const { language } = useLanguage();
  const t = useT();

  const [jobs, setJobs] = useState<RenderJobDetails[]>([]);
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // For inline video player modal
  const [activeVideo, setActiveVideo] = useState<{ filename: string; url: string } | null>(null);

  // Fetch all jobs
  async function loadJobs() {
    try {
      const allJobs = await api.getAllRenders();
      setJobs(allJobs);
      setErrorMsg(null);
    } catch (err) {
      console.error('Failed to load render jobs:', err);
      setErrorMsg(t('jobs.failedToLoad'));
    } finally {
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
      return t('jobs.fullSurah');
    }
    if (job.from_ayah === job.to_ayah) {
      return t('jobs.ayahLabel', { number: job.from_ayah });
    }
    return t('jobs.ayahsRange', { from: job.from_ayah, to: job.to_ayah });
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'completed':
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            {t('common.status.completed')}
          </span>
        );
      case 'failed':
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-red-500/10 text-red-400 border border-red-500/20">
            {t('common.status.failed')}
          </span>
        );
      case 'running':
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20 animate-pulse">
            {t('common.status.running')}
          </span>
        );
      case 'cancelling':
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse">
            {t('common.status.cancelling')}
          </span>
        );
      case 'cancelled':
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-800 text-zinc-500 border border-zinc-700/50">
            {t('common.status.cancelled')}
          </span>
        );
      case 'queued':
      default:
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-800 text-zinc-400 border border-zinc-700">
            {t('common.status.queued')}
          </span>
        );
    }
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Title */}
      <div className="border-b border-zinc-900 pb-4">
        <h1 className="text-2xl font-extrabold text-zinc-150 flex items-center justify-between">
          <span>{t('jobs.pageTitle')}</span>
        </h1>
        <p className="text-xs text-zinc-500 mt-1">
          {t('jobs.pageDescription')}
        </p>
      </div>

      {errorMsg && (
        <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl text-sm font-mono">
          {errorMsg}
        </div>
      )}

      {loading ? (
        <div className="flex flex-col items-center justify-center min-h-[30vh]">
          <div className="w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
          <span className="text-xs text-zinc-500 mt-3 font-mono">{t('jobs.loadingQueue')}</span>
        </div>
      ) : jobs.length === 0 ? (
        <div className="border border-zinc-900 bg-zinc-950/40 rounded-2xl p-12 text-center flex flex-col items-center justify-center gap-3">
          <div className="w-10 h-10 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-400">
            📭
          </div>
          <h3 className="font-semibold text-zinc-350">{t('jobs.noJobs')}</h3>
          <p className="text-xs text-zinc-500 max-w-sm">
            {t('jobs.noJobsDescription')}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {jobs.map((job) => (
            <div
              key={job.uuid}
              className="bg-zinc-950 border border-zinc-900 rounded-2xl p-5 flex flex-col justify-between gap-4 shadow-xl relative overflow-hidden"
            >
              {/* Glow Highlight */}
              {job.status === 'running' && (
                <div className="absolute top-0 end-0 w-24 h-24 bg-blue-500/5 blur-2xl pointer-events-none rounded-full"></div>
              )}
              {job.status === 'completed' && (
                <div className="absolute top-0 end-0 w-24 h-24 bg-emerald-500/5 blur-2xl pointer-events-none rounded-full"></div>
              )}

              {/* Upper Section */}
              <div className="flex flex-col gap-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="flex flex-col">
                    <span className="text-sm font-extrabold text-zinc-200">
                      {t('jobs.surahLabel', { number: job.surah })}
                    </span>
                    <span className="text-xs text-zinc-500 font-mono mt-0.5">
                      {t('jobs.reciterLabel', { name: language === 'ar' ? job.reciter_ar : job.reciter })}
                    </span>
                  </div>
                  {getStatusBadge(job.status)}
                </div>

                <div className="flex gap-2 text-2xs font-mono">
                  <div className="bg-zinc-900/65 border border-zinc-900/40 px-3 py-2 rounded-xl font-semibold flex-1 text-center text-zinc-350">
                    {t('jobs.scope')}: {formatScope(job)}
                  </div>
                  <div className={`px-3 py-2 rounded-xl font-bold flex-1 text-center border uppercase ${
                    job.layout === 'youtube'
                      ? 'bg-blue-500/10 text-blue-400 border-blue-500/20'
                      : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                  }`}>
                    {job.layout === 'youtube' ? `${t('jobs.youtube')} (${job.max_lines ?? 1}L)` : t('jobs.reels')}
                  </div>
                </div>

                {/* Translation Info */}
                <div className="bg-zinc-900/40 border border-zinc-900/30 p-2.5 rounded-xl text-2xs font-mono flex flex-col gap-1 text-zinc-400">
                  <div className="flex justify-between items-center">
                    <span className="text-zinc-500">{t('jobs.translation')}:</span>
                    {job.with_translation ? (
                      <span className="text-emerald-400 font-bold">{t('common.enabled')}</span>
                    ) : (
                      <span className="text-zinc-500">{t('common.disabled')}</span>
                    )}
                  </div>
                  {job.with_translation && (
                    <div className="flex justify-between items-center border-t border-zinc-900/50 pt-1 mt-0.5">
                      <span className="text-zinc-500">{t('jobs.source')}:</span>
                      <span className="text-zinc-300 font-semibold uppercase">
                        {job.translation_source === 'sahih_international'
                          ? t('render.sahihInternational')
                          : job.translation_source || 'Unknown'}
                      </span>
                    </div>
                  )}
                </div>

                {/* Progress bar */}
                {(job.status === 'running' || job.status === 'queued' || job.status === 'completed' || job.status === 'cancelling') && (
                  <div className="flex flex-col gap-1.5 mt-1">
                    <div className="flex items-center justify-between text-2xs font-mono text-zinc-500">
                      <span>{t('jobs.progress')}</span>
                      <span>{job.progress}%</span>
                    </div>
                    <div className="w-full bg-zinc-900 rounded-full h-1.5 overflow-hidden">
                      <div
                        className={`h-full transition-all duration-500 ${
                          job.status === 'completed' ? 'bg-emerald-500' : (job.status === 'cancelling' ? 'bg-amber-500' : 'bg-blue-500')
                        }`}
                        style={{ width: `${job.progress}%` }}
                      ></div>
                    </div>
                  </div>
                )}

                {/* Error Banner */}
                {job.status === 'failed' && (
                  <div className="bg-red-500/10 border border-red-500/20 text-red-400 p-3 rounded-xl text-xs font-mono break-words">
                    <strong>{t('jobs.error')}:</strong> {job.error}
                  </div>
                )}

                {/* Cancelled Banner */}
                {job.status === 'cancelled' && (
                  <div className="bg-zinc-900/60 border border-zinc-800 text-zinc-500 p-3 rounded-xl text-xs font-mono">
                    ℹ️ {t('jobs.renderCancelledByUser')}
                  </div>
                )}
              </div>

              {/* Action Buttons */}
              <div className="border-t border-zinc-900 pt-3 flex items-center justify-between gap-3">
                <span className="text-[10px] text-zinc-600 font-mono">
                  {new Date(job.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                </span>

                <div className="flex gap-2">
                  {(job.status === 'queued' || job.status === 'running') && (
                    <button
                      onClick={() => handleCancel(job.uuid)}
                      className="bg-red-500/10 hover:bg-red-500/20 text-red-450 text-xs px-3 py-1.5 rounded-lg border border-red-500/30 transition-all font-semibold active:scale-95 cursor-pointer"
                    >
                      {t('jobs.cancelRender')}
                    </button>
                  )}

                  {job.status === 'cancelling' && (
                    <button
                      disabled
                      className="bg-amber-500/10 text-amber-450 text-xs px-3 py-1.5 rounded-lg border border-amber-500/20 opacity-70 cursor-not-allowed font-semibold"
                    >
                      {t('jobs.cancellingButton')}
                    </button>
                  )}

                  {job.status === 'completed' && job.url && job.filename && (
                    <>
                      <button
                        onClick={() => setActiveVideo({ filename: job.filename!, url: `${api.baseUrl}${job.url}` })}
                        className="bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-xs px-3 py-1.5 rounded-lg border border-emerald-500/30 transition-all font-semibold active:scale-95 cursor-pointer"
                      >
                        {t('common.play')}
                      </button>
                      <a
                        href={`${api.baseUrl}${job.url}`}
                        download={job.filename}
                        className="bg-zinc-900 hover:bg-zinc-800 text-zinc-200 text-xs px-3 py-1.5 rounded-lg border border-zinc-800 transition-all font-semibold active:scale-95 text-center flex items-center justify-center"
                      >
                        {t('common.download')}
                      </a>
                    </>
                  )}

                  {/* Retry Option */}
                  {job.status === 'failed' && (
                    <button
                      disabled
                      className="bg-zinc-900 text-zinc-500 text-xs px-3 py-1.5 rounded-lg border border-zinc-800 opacity-55 cursor-not-allowed"
                      title={t('jobs.retryComingSoon')}
                    >
                      {t('jobs.retry')}
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Video Modal Player */}
      {activeVideo && (
        <div className="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex items-center justify-center p-4">
          <div className="bg-zinc-950 border border-zinc-900 max-w-4xl w-full rounded-2xl overflow-hidden shadow-2xl relative flex flex-col">
            <div className="px-6 py-4 border-b border-zinc-900 flex items-center justify-between">
              <h3 className="font-semibold text-zinc-200 truncate font-mono text-sm">{activeVideo.filename}</h3>
              <button
                onClick={() => setActiveVideo(null)}
                className="text-zinc-500 hover:text-zinc-200 text-lg font-bold p-1 bg-zinc-900 rounded-lg w-8 h-8 flex items-center justify-center border border-zinc-800"
              >
                ✕
              </button>
            </div>
            <div className="bg-black aspect-video flex items-center justify-center p-2">
              <video
                src={activeVideo.url}
                controls
                autoPlay
                className="w-full h-full rounded-xl object-contain shadow-inner"
              />
            </div>
            <div className="px-6 py-3 border-t border-zinc-900 flex justify-end">
              <a
                href={activeVideo.url}
                download={activeVideo.filename}
                className="bg-emerald-500 text-black hover:bg-emerald-400 font-bold px-4 py-2 rounded-xl text-xs shadow-md transition-all active:scale-95"
              >
                {t('jobs.downloadVideoFile')}
              </a>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
