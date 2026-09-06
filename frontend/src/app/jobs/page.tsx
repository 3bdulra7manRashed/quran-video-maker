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
          <span className="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-405 border border-emerald-200 dark:border-emerald-900">
            {t('common.status.completed')}
          </span>
        );
      case 'failed':
        return (
          <span className="px-2 py-0.5 rounded text-xs font-semibold bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900">
            {t('common.status.failed')}
          </span>
        );
      case 'running':
        return (
          <span className="px-2 py-0.5 rounded text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/20 dark:text-blue-400 border border-blue-200 dark:border-blue-900">
            {t('common.status.running')}
          </span>
        );
      case 'cancelling':
        return (
          <span className="px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400 border border-amber-200 dark:border-amber-900">
            {t('common.status.cancelling')}
          </span>
        );
      case 'cancelled':
        return (
          <span className="px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
            {t('common.status.cancelled')}
          </span>
        );
      case 'queued':
      default:
        return (
          <span className="px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-650 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
            {t('common.status.queued')}
          </span>
        );
    }
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Title */}
      <div className="border-b border-slate-200 dark:border-slate-800 pb-4">
        <h1 className="text-2xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center justify-between">
          <span>{t('jobs.pageTitle')}</span>
        </h1>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
          {t('jobs.pageDescription')}
        </p>
      </div>

      {errorMsg && (
        <div className="bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900/50 px-4 py-3 rounded-xl text-sm font-mono">
          {errorMsg}
        </div>
      )}

      {loading ? (
        <div className="flex flex-col items-center justify-center min-h-[30vh]">
          <div className="w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full"></div>
          <span className="text-xs text-slate-500 dark:text-slate-400 mt-3 font-mono">{t('jobs.loadingQueue')}</span>
        </div>
      ) : jobs.length === 0 ? (
        <div className="border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 rounded-xl p-12 text-center flex flex-col items-center justify-center gap-3">
          <div className="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-950 border border-slate-250 dark:border-slate-800 flex items-center justify-center text-slate-400">
            📭
          </div>
          <h3 className="font-semibold text-slate-700 dark:text-slate-350">{t('jobs.noJobs')}</h3>
          <p className="text-xs text-slate-500 dark:text-slate-450 max-w-sm">
            {t('jobs.noJobsDescription')}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {jobs.map((job) => (
            <div
              key={job.uuid}
              className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 flex flex-col justify-between gap-4 shadow-sm"
            >
              {/* Upper Section */}
              <div className="flex flex-col gap-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="flex flex-col">
                    <span className="text-sm font-extrabold text-slate-900 dark:text-slate-100">
                      {t('jobs.surahLabel', { number: job.surah })}
                    </span>
                    <span className="text-xs text-slate-550 dark:text-slate-400 font-mono mt-0.5">
                      {t('jobs.reciterLabel', { name: language === 'ar' ? job.reciter_ar : job.reciter })}
                    </span>
                  </div>
                  {getStatusBadge(job.status)}
                </div>

                <div className="flex gap-2 text-[10px] font-mono">
                  <div className="bg-slate-50 dark:bg-slate-950 border border-slate-150 dark:border-slate-850 px-3 py-2 rounded-xl font-semibold flex-1 text-center text-slate-700 dark:text-slate-300">
                    {t('jobs.scope')}: {formatScope(job)}
                  </div>
                  <div className={`px-3 py-2 rounded-xl font-bold flex-1 text-center border uppercase ${
                    job.layout === 'youtube'
                      ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/20 dark:text-blue-400 dark:border-blue-900'
                      : 'bg-emerald-55 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900'
                  }`}>
                    {job.layout === 'youtube' ? `${t('jobs.youtube')} (${job.max_lines ?? 1}L)` : t('jobs.reels')}
                  </div>
                </div>

                {/* Translation Info */}
                <div className="bg-slate-50 dark:bg-slate-950 border border-slate-150 dark:border-slate-850 p-2.5 rounded-xl text-[10px] font-mono flex flex-col gap-1 text-slate-600 dark:text-slate-400">
                  <div className="flex justify-between items-center">
                    <span className="text-slate-500">{t('jobs.translation')}:</span>
                    {job.with_translation ? (
                      <span className="text-emerald-600 dark:text-emerald-400 font-bold">{t('common.enabled')}</span>
                    ) : (
                      <span className="text-slate-500">{t('common.disabled')}</span>
                    )}
                  </div>
                  {job.with_translation && (
                    <div className="flex justify-between items-center border-t border-slate-200 dark:border-slate-850 pt-1 mt-0.5">
                      <span className="text-slate-500">{t('jobs.source')}:</span>
                      <span className="text-slate-700 dark:text-slate-300 font-semibold uppercase">
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
                    <div className="flex items-center justify-between text-[10px] font-mono text-slate-500">
                      <span>{t('jobs.progress')}</span>
                      <span>{job.progress}%</span>
                    </div>
                    <div className="w-full bg-slate-100 dark:bg-slate-950 rounded-full h-1.5 overflow-hidden">
                      <div
                        className={`h-full ${
                          job.status === 'completed' ? 'bg-emerald-600' : (job.status === 'cancelling' ? 'bg-amber-500' : 'bg-blue-600')
                        }`}
                        style={{ width: `${job.progress}%` }}
                      ></div>
                    </div>
                  </div>
                )}

                {/* Error Banner */}
                {job.status === 'failed' && (
                  <div className="bg-red-50 text-red-750 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900/50 p-3 rounded-xl text-xs font-mono break-words">
                    <strong>{t('jobs.error')}:</strong> {job.error}
                  </div>
                )}

                {/* Cancelled Banner */}
                {job.status === 'cancelled' && (
                  <div className="bg-slate-50 border border-slate-200 dark:bg-slate-950 dark:border-slate-850 text-slate-500 p-3 rounded-xl text-xs font-mono">
                    ℹ️ {t('jobs.renderCancelledByUser')}
                  </div>
                )}
              </div>

              {/* Action Buttons */}
              <div className="border-t border-slate-100 dark:border-slate-800/60 pt-3 flex items-center justify-between gap-3">
                <span className="text-[10px] text-slate-400 dark:text-slate-550 font-mono">
                  {new Date(job.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                </span>

                <div className="flex gap-2">
                  {(job.status === 'queued' || job.status === 'running') && (
                    <button
                      onClick={() => handleCancel(job.uuid)}
                      className="bg-red-50 hover:bg-red-100 text-red-650 text-xs px-3 py-1.5 rounded-lg border border-red-200 dark:bg-red-950/20 dark:hover:bg-red-950/40 dark:text-red-400 dark:border-red-900/30 font-semibold cursor-pointer"
                    >
                      {t('jobs.cancelRender')}
                    </button>
                  )}

                  {job.status === 'cancelling' && (
                    <button
                      disabled
                      className="bg-amber-50 text-amber-650 text-xs px-3 py-1.5 rounded-lg border border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30 opacity-70 cursor-not-allowed font-semibold"
                    >
                      {t('jobs.cancellingButton')}
                    </button>
                  )}

                  {job.status === 'completed' && job.url && job.filename && (
                    <>
                      <button
                        onClick={() => setActiveVideo({ filename: job.filename!, url: `${api.baseUrl}${job.url}` })}
                        className="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs px-3 py-1.5 rounded-lg border border-emerald-200 dark:bg-emerald-950/20 dark:hover:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900/30 font-semibold cursor-pointer"
                      >
                        {t('common.play')}
                      </button>
                      <a
                        href={`${api.baseUrl}${job.url}`}
                        download={job.filename}
                        className="bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 text-xs px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 font-semibold text-center flex items-center justify-center"
                      >
                        {t('common.download')}
                      </a>
                    </>
                  )}

                  {/* Retry Option */}
                  {job.status === 'failed' && (
                    <button
                      disabled
                      className="bg-slate-50 dark:bg-slate-950 text-slate-400 dark:text-slate-600 text-xs px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-850 opacity-55 cursor-not-allowed"
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
        <div className="fixed inset-0 z-50 bg-black/85 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 max-w-4xl w-full rounded-xl overflow-hidden shadow-2xl relative flex flex-col">
            <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <h3 className="font-semibold text-slate-900 dark:text-slate-100 truncate font-mono text-sm">{activeVideo.filename}</h3>
              <button
                onClick={() => setActiveVideo(null)}
                className="text-slate-400 hover:text-slate-600 dark:text-slate-550 dark:hover:text-slate-350 text-lg font-bold p-1 bg-slate-50 dark:bg-slate-950 rounded-lg w-8 h-8 flex items-center justify-center border border-slate-200 dark:border-slate-850 cursor-pointer"
              >
                ✕
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
            <div className="px-6 py-3 border-t border-slate-200 dark:border-slate-800 flex justify-end">
              <a
                href={activeVideo.url}
                download={activeVideo.filename}
                className="bg-emerald-600 text-white hover:bg-emerald-500 font-bold px-4 py-2 rounded-xl text-xs"
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
