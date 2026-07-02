'use client';

import React, { useEffect, useState, useMemo } from 'react';
import { useApi } from '@/context/ApiContext';
import { VideoFile } from '@/services/api';
import { useLanguage } from '@/hooks/useLanguage';
import { useT } from '@/hooks/useT';
import { formatDate } from '@/utils/formatDate';

export default function VideosPage() {
  const { api } = useApi();
  const { language } = useLanguage();
  const t = useT();

  const [videos, setVideos] = useState<VideoFile[]>([]);
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [toastMsg, setToastMsg] = useState<string | null>(null);

  // Search, Filters & Sorting States
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedReciter, setSelectedReciter] = useState('all');
  const [selectedScope, setSelectedScope] = useState('all');
  const [sortBy, setSortBy] = useState('newest');

  // Video Modals
  const [activeVideo, setActiveVideo] = useState<{ filename: string; url: string } | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<VideoFile | null>(null);
  const [deleting, setDeleting] = useState(false);

  async function loadVideos() {
    try {
      const list = await api.getVideos();
      setVideos(list);
      setErrorMsg(null);
    } catch (err) {
      console.error('Failed to load videos:', err);
      setErrorMsg(t('common.videos.failedToLoad'));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadVideos();
  }, []);

  const showToast = (msg: string) => {
    setToastMsg(msg);
    setTimeout(() => setToastMsg(null), 4000);
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await api.deleteVideo(deleteTarget.filename);
      showToast(t('common.videos.deleteSuccess'));
      setDeleteTarget(null);
      await loadVideos();
    } catch (err: any) {
      console.error('Deletion failed:', err);
      alert(err.message || t('common.videos.deleteFailed'));
    } finally {
      setDeleting(false);
    }
  };

  // Extract unique reciters names dynamically
  const uniqueReciters = useMemo(() => {
    const names = new Set<string>();
    videos.forEach((v) => {
      if (v.reciter_en) names.add(v.reciter_en);
    });
    return Array.from(names);
  }, [videos]);

  // Compute filtered & sorted list
  const filteredVideos = useMemo(() => {
    let result = [...videos];

    // 1. Text Search Filter
    if (searchTerm.trim() !== '') {
      const query = searchTerm.toLowerCase();
      result = result.filter((v) => {
        const scopeText = v.from_ayah === null || v.to_ayah === null
          ? 'full surah הסورة كاملة'
          : v.from_ayah === v.to_ayah
            ? `ayah ${v.from_ayah} الآية`
            : `ayahs range ${v.from_ayah} ${v.to_ayah} الآيات`;

        return (
          v.filename.toLowerCase().includes(query) ||
          v.reciter.toLowerCase().includes(query) ||
          v.reciter_en.toLowerCase().includes(query) ||
          String(v.surah).includes(query) ||
          scopeText.toLowerCase().includes(query)
        );
      });
    }

    // 2. Reciter Filter
    if (selectedReciter !== 'all') {
      result = result.filter((v) => v.reciter_en === selectedReciter);
    }

    // 3. Scope Filter
    if (selectedScope !== 'all') {
      result = result.filter((v) => {
        const isFull = v.from_ayah === null && v.to_ayah === null;
        const isSingle = v.from_ayah !== null && v.from_ayah === v.to_ayah;
        const isRange = v.from_ayah !== null && v.from_ayah !== v.to_ayah;

        if (selectedScope === 'full') return isFull;
        if (selectedScope === 'single') return isSingle;
        if (selectedScope === 'range') return isRange;
        return true;
      });
    }

    // 4. Sorting
    result.sort((a, b) => {
      if (sortBy === 'newest') {
        return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
      }
      if (sortBy === 'oldest') {
        return new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
      }
      if (sortBy === 'surah') {
        return a.surah - b.surah;
      }
      if (sortBy === 'reciter') {
        return a.reciter_en.localeCompare(b.reciter_en);
      }
      if (sortBy === 'duration') {
        return b.duration_seconds - a.duration_seconds;
      }
      return 0;
    });

    return result;
  }, [videos, searchTerm, selectedReciter, selectedScope, sortBy]);

  const formatScope = (v: VideoFile) => {
    if (v.from_ayah === null || v.to_ayah === null) {
      return t('common.videos.formatFullSurah');
    }
    if (v.from_ayah === v.to_ayah) {
      return t('common.videos.formatAyah', { number: v.from_ayah });
    }
    return t('common.videos.formatAyahs', { from: v.from_ayah, to: v.to_ayah });
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Title */}
      <div className="border-b border-slate-200 dark:border-slate-800 pb-4">
        <h1 className="text-2xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center justify-between">
          <span>{t('common.videos.pageTitle')}</span>
        </h1>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
          {t('common.videos.pageDescription')}
        </p>
      </div>

      {toastMsg && (
        <div className="fixed bottom-6 end-6 z-50 bg-emerald-600 text-white font-bold px-5 py-3 rounded-xl shadow-lg flex items-center gap-2">
          <span>✓</span>
          <span>{toastMsg}</span>
        </div>
      )}

      {errorMsg && (
        <div className="bg-red-50 text-red-750 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900/50 px-4 py-3 rounded-xl text-sm font-mono">
          {errorMsg}
        </div>
      )}

      {/* Control Bar: Search & Filters */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 flex flex-col gap-4">
        {/* Search */}
        <div className="relative">
          <input
            type="text"
            placeholder={t('common.videos.searchPlaceholder')}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 ps-10 pe-4 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 font-medium text-sm"
          />
          <div className="absolute inset-y-0 start-3 flex items-center pointer-events-none text-slate-400">
            🔍
          </div>
          {searchTerm && (
            <button
              onClick={() => setSearchTerm('')}
              className="absolute inset-y-0 end-3 flex items-center text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-250 text-xs"
            >
              {t('common.clear')}
            </button>
          )}
        </div>

        {/* Filter selectors */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          {/* Reciter Filter */}
          <div className="flex flex-col gap-1.5">
            <label className="text-[10px] font-bold text-slate-500 dark:text-slate-400 font-mono">RECITER</label>
            <select
              value={selectedReciter}
              onChange={(e) => setSelectedReciter(e.target.value)}
              className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl text-xs focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 cursor-pointer"
            >
              <option value="all">{t('common.videos.allReciters')}</option>
              {uniqueReciters.map((name) => (
                <option key={name} value={name}>
                  {name}
                </option>
              ))}
            </select>
          </div>

          {/* Scope Filter */}
          <div className="flex flex-col gap-1.5">
            <label className="text-[10px] font-bold text-slate-500 dark:text-slate-400 font-mono">SCOPE</label>
            <select
              value={selectedScope}
              onChange={(e) => setSelectedScope(e.target.value)}
              className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl text-xs focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 cursor-pointer"
            >
              <option value="all">{t('common.videos.allScopes')}</option>
              <option value="full">{t('common.videos.fullSurah')}</option>
              <option value="single">{t('common.videos.singleAyah')}</option>
              <option value="range">{t('common.videos.ayahRange')}</option>
            </select>
          </div>

          {/* Sorting */}
          <div className="flex flex-col gap-1.5">
            <label className="text-[10px] font-bold text-slate-500 dark:text-slate-400 font-mono">SORT BY</label>
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value)}
              className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl text-xs focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 cursor-pointer"
            >
              <option value="newest">{t('common.videos.sortNewest')}</option>
              <option value="oldest">{t('common.videos.sortOldest')}</option>
              <option value="surah">{t('common.videos.sortSurah')}</option>
              <option value="reciter">{t('common.videos.sortReciter')}</option>
              <option value="duration">{t('common.videos.sortDuration')}</option>
            </select>
          </div>
        </div>
      </div>

      {loading ? (
        <div className="flex flex-col items-center justify-center min-h-[30vh]">
          <div className="w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full"></div>
          <span className="text-xs text-slate-500 dark:text-slate-400 mt-3 font-mono">Loading videos...</span>
        </div>
      ) : filteredVideos.length === 0 ? (
        <div className="border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 rounded-xl p-12 text-center flex flex-col items-center justify-center gap-3">
          <div className="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 flex items-center justify-center text-slate-400">
            🔍
          </div>
          <h3 className="font-semibold text-slate-700 dark:text-slate-350">{t('common.videos.noMatching')}</h3>
          <p className="text-xs text-slate-500 dark:text-slate-450 max-w-sm">
            {t('common.videos.noMatchingDescription')}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filteredVideos.map((v) => (
            <div
              key={v.filename}
              className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 flex flex-col justify-between gap-4 shadow-sm group"
            >
              <div className="flex flex-col gap-2.5">
                {/* Filename Header */}
                <div className="flex flex-col gap-1 border-b border-slate-100 dark:border-slate-850 pb-2">
                  <div className="flex justify-between items-start gap-2">
                    <span className="text-xs font-bold text-slate-900 dark:text-slate-100 font-mono truncate flex-1" title={v.filename}>
                      {v.filename}
                    </span>
                    <span className={`text-[9px] px-1.5 py-0.5 rounded font-bold font-mono uppercase shrink-0 ${
                      v.layout === 'youtube'
                        ? 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/20 dark:text-blue-400 dark:border-blue-900'
                        : 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900'
                    }`}>
                      {v.layout === 'youtube' ? `YouTube (${v.max_lines ?? 1}L)` : 'Reels'}
                    </span>
                  </div>
                  <span className="text-[10px] text-slate-450 dark:text-slate-550 font-mono">
                    {formatDate(v.created_at, language)}
                  </span>
                </div>

                {/* Details */}
                <div className="grid grid-cols-2 gap-2 text-[10px] font-mono">
                  <div className="bg-slate-50 dark:bg-slate-950 p-2 rounded-lg border border-slate-100 dark:border-slate-850/60 flex flex-col gap-0.5">
                    <span className="text-slate-400 dark:text-slate-500 text-[8px]">{t('common.videos.reciter')}</span>
                    <span className="text-slate-700 dark:text-slate-300 font-semibold font-sans truncate">{language === 'ar' ? v.reciter : v.reciter_en}</span>
                  </div>
                  <div className="bg-slate-50 dark:bg-slate-950 p-2 rounded-lg border border-slate-100 dark:border-slate-850/60 flex flex-col gap-0.5">
                    <span className="text-slate-400 dark:text-slate-500 text-[8px]">{t('common.videos.surah')}</span>
                    <span className="text-slate-700 dark:text-slate-300 font-semibold">{t('jobs.surahLabel', { number: v.surah })}</span>
                  </div>
                  <div className="bg-slate-50 dark:bg-slate-950 p-2 rounded-lg border border-slate-100 dark:border-slate-850/60 flex flex-col gap-0.5">
                    <span className="text-slate-400 dark:text-slate-500 text-[8px]">{t('common.videos.scope')}</span>
                    <span className="text-slate-700 dark:text-slate-300 font-semibold">{formatScope(v)}</span>
                  </div>
                  <div className="bg-slate-50 dark:bg-slate-950 p-2 rounded-lg border border-slate-100 dark:border-slate-850/60 flex flex-col gap-0.5">
                    <span className="text-slate-400 dark:text-slate-500 text-[8px]">{t('common.videos.duration')}</span>
                    <span className="text-slate-700 dark:text-slate-300 font-semibold">{v.duration_human}</span>
                  </div>
                </div>
              </div>

              {/* Actions */}
              <div className="flex gap-2 justify-end border-t border-slate-100 dark:border-slate-850 pt-3">
                <button
                  onClick={() => setActiveVideo({ filename: v.filename, url: `${api.baseUrl}${v.url}` })}
                  className="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[10px] px-2.5 py-1.5 rounded-lg border border-emerald-200 dark:bg-emerald-950/20 dark:hover:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900/30 font-bold cursor-pointer flex-1 flex items-center justify-center gap-1"
                >
                  <span>▶ Play</span>
                </button>
                <a
                  href={`${api.baseUrl}${v.url}`}
                  download={v.filename}
                  className="bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-850 dark:hover:bg-slate-700 dark:text-slate-200 text-[10px] px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 font-semibold flex-1 text-center flex items-center justify-center gap-1"
                >
                  <span>⬇ Download</span>
                </a>
                <button
                  onClick={() => setDeleteTarget(v)}
                  className="bg-red-50 hover:bg-red-100 text-red-650 text-[10px] px-2.5 py-1.5 rounded-lg border border-red-200 dark:bg-red-950/20 dark:hover:bg-red-950/40 dark:text-red-400 dark:border-red-900/30 font-semibold cursor-pointer flex-1 flex items-center justify-center gap-1"
                >
                  <span>🗑 Delete</span>
                </button>
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
                className="text-slate-400 hover:text-slate-655 dark:text-slate-550 dark:hover:text-slate-350 text-lg font-bold p-1 bg-slate-50 dark:bg-slate-950 rounded-lg w-8 h-8 flex items-center justify-center border border-slate-200 dark:border-slate-850 cursor-pointer"
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
                {t('common.videos.downloadVideoFile')}
              </a>
            </div>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {deleteTarget && (
        <div className="fixed inset-0 z-50 bg-black/85 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 max-w-md w-full rounded-xl p-6 shadow-2xl flex flex-col gap-4">
            <div className="flex items-center gap-3 border-b border-slate-200 dark:border-slate-800 pb-3">
              <div className="w-10 h-10 bg-red-50 text-red-600 rounded-xl flex items-center justify-center border border-red-200 dark:bg-red-950/20 dark:border-red-900/30 text-lg">
                ⚠️
              </div>
              <div className="flex flex-col">
                <h3 className="font-bold text-slate-850 dark:text-slate-250">{t('common.videos.deleteVideo')}</h3>
              </div>
            </div>

            <div className="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-xl p-4 flex flex-col gap-2 font-sans text-xs">
              <div className="flex justify-between">
                <span className="text-slate-450 dark:text-slate-500">{t('common.videos.surah')}</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{t('jobs.surahLabel', { number: deleteTarget.surah })}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-450 dark:text-slate-500">{t('common.videos.reciter')}</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{language === 'ar' ? deleteTarget.reciter : deleteTarget.reciter_en}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-450 dark:text-slate-500">{t('common.videos.scope')}</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{formatScope(deleteTarget)}</span>
              </div>
            </div>

            <p className="text-xs text-red-650 dark:text-red-400 font-medium">
              {t('common.videos.deleteWarning')}
            </p>

            <div className="flex gap-3 justify-end mt-2">
              <button
                type="button"
                onClick={() => setDeleteTarget(null)}
                disabled={deleting}
                className="bg-slate-100 hover:bg-slate-200 text-slate-850 border border-slate-200 dark:bg-slate-950 dark:hover:bg-slate-850 dark:text-slate-300 dark:border-slate-800 px-4 py-2 rounded-xl text-xs disabled:opacity-50 cursor-pointer font-semibold"
              >
                {t('common.cancel')}
              </button>
              <button
                type="button"
                onClick={handleDelete}
                disabled={deleting}
                className="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-xl text-xs disabled:opacity-50 flex items-center gap-1 cursor-pointer font-bold"
              >
                {deleting ? t('common.videos.deleting') : t('common.delete')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
