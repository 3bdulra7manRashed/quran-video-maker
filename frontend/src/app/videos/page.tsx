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
  const isAr = language === 'ar';
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
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-surface-variant/30 pb-4">
        <div>
          <h1 className="text-xl sm:text-2xl font-extrabold text-on-surface flex items-center gap-2">
            <span className="material-symbols-outlined text-primary text-[28px]">video_library</span>
            <span>{t('common.videos.pageTitle')}</span>
          </h1>
          <p className="text-xs text-on-surface-variant mt-1">
            {t('common.videos.pageDescription')}
          </p>
        </div>

        <button
          onClick={() => loadVideos()}
          disabled={loading}
          className="self-start sm:self-auto flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-semibold border border-surface-variant/40 transition-colors cursor-pointer"
        >
          <span className={`material-symbols-outlined text-[16px] text-primary ${loading ? 'animate-spin' : ''}`}>
            sync
          </span>
          <span>{isAr ? 'تحديث المكتبة' : 'Refresh'}</span>
        </button>
      </div>

      {toastMsg && (
        <div className="fixed bottom-6 end-6 z-50 bg-secondary text-on-secondary font-bold px-4 py-2.5 rounded-xl shadow-xl flex items-center gap-2 text-xs">
          <span className="material-symbols-outlined text-[16px]">check_circle</span>
          <span>{toastMsg}</span>
        </div>
      )}

      {errorMsg && (
        <div className="bg-red-500/10 text-red-400 border border-red-500/30 px-4 py-3 rounded-xl text-xs font-mono">
          {errorMsg}
        </div>
      )}

      {/* Control Bar: Search & Filters */}
      <div className="bg-surface-container border border-surface-variant/40 rounded-xl p-4 flex flex-col gap-3 shadow-sm">
        {/* Search */}
        <div className="relative">
          <input
            type="text"
            placeholder={t('common.videos.searchPlaceholder')}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface placeholder:text-on-surface-variant/40 ps-9 pe-8 py-2 rounded-xl focus:outline-none focus:border-primary text-xs font-medium transition-colors"
          />
          <div className="absolute inset-y-0 start-2.5 flex items-center pointer-events-none text-on-surface-variant">
            <span className="material-symbols-outlined text-[18px]">search</span>
          </div>
          {searchTerm && (
            <button
              onClick={() => setSearchTerm('')}
              className="absolute inset-y-0 end-2.5 flex items-center text-on-surface-variant hover:text-on-surface text-xs cursor-pointer"
            >
              <span className="material-symbols-outlined text-[16px]">close</span>
            </button>
          )}
        </div>

        {/* Filter selectors */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          {/* Reciter Filter */}
          <div className="flex flex-col gap-1">
            <label className="text-[10px] font-bold text-on-surface-variant font-mono uppercase">
              {isAr ? 'القارئ' : 'RECITER'}
            </label>
            <select
              value={selectedReciter}
              onChange={(e) => setSelectedReciter(e.target.value)}
              className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface px-3 py-1.5 rounded-lg text-xs focus:outline-none focus:border-primary cursor-pointer"
            >
              <option value="all" className="bg-zinc-900 text-white">{t('common.videos.allReciters')}</option>
              {uniqueReciters.map((name) => (
                <option key={name} value={name} className="bg-zinc-900 text-white">
                  {name}
                </option>
              ))}
            </select>
          </div>

          {/* Scope Filter */}
          <div className="flex flex-col gap-1">
            <label className="text-[10px] font-bold text-on-surface-variant font-mono uppercase">
              {isAr ? 'النطاق' : 'SCOPE'}
            </label>
            <select
              value={selectedScope}
              onChange={(e) => setSelectedScope(e.target.value)}
              className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface px-3 py-1.5 rounded-lg text-xs focus:outline-none focus:border-primary cursor-pointer"
            >
              <option value="all" className="bg-zinc-900 text-white">{t('common.videos.allScopes')}</option>
              <option value="full" className="bg-zinc-900 text-white">{t('common.videos.fullSurah')}</option>
              <option value="single" className="bg-zinc-900 text-white">{t('common.videos.singleAyah')}</option>
              <option value="range" className="bg-zinc-900 text-white">{t('common.videos.ayahRange')}</option>
            </select>
          </div>

          {/* Sorting */}
          <div className="flex flex-col gap-1">
            <label className="text-[10px] font-bold text-on-surface-variant font-mono uppercase">
              {isAr ? 'الترتيب حسب' : 'SORT BY'}
            </label>
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value)}
              className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface px-3 py-1.5 rounded-lg text-xs focus:outline-none focus:border-primary cursor-pointer"
            >
              <option value="newest" className="bg-zinc-900 text-white">{t('common.videos.sortNewest')}</option>
              <option value="oldest" className="bg-zinc-900 text-white">{t('common.videos.sortOldest')}</option>
              <option value="surah" className="bg-zinc-900 text-white">{t('common.videos.sortSurah')}</option>
              <option value="reciter" className="bg-zinc-900 text-white">{t('common.videos.sortReciter')}</option>
              <option value="duration" className="bg-zinc-900 text-white">{t('common.videos.sortDuration')}</option>
            </select>
          </div>
        </div>
      </div>

      {loading ? (
        <div className="flex flex-col items-center justify-center min-h-[30vh]">
          <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
          <span className="text-xs text-on-surface-variant mt-3 font-mono">Loading videos...</span>
        </div>
      ) : filteredVideos.length === 0 ? (
        <div className="border border-surface-variant/40 bg-surface-container rounded-xl p-12 text-center flex flex-col items-center justify-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-surface-container-lowest border border-surface-variant/30 flex items-center justify-center text-primary">
            <span className="material-symbols-outlined text-[24px]">video_file</span>
          </div>
          <h3 className="font-bold text-on-surface text-sm">{t('common.videos.noMatching')}</h3>
          <p className="text-xs text-on-surface-variant max-w-sm">
            {t('common.videos.noMatchingDescription')}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredVideos.map((v) => (
            <div
              key={v.filename}
              className="bg-surface-container border border-surface-variant/30 hover:border-surface-variant/60 rounded-xl p-4 flex flex-col justify-between gap-3 shadow-sm transition-all group"
            >
              <div className="flex flex-col gap-2.5">
                {/* Filename Header */}
                <div className="flex flex-col gap-1 border-b border-surface-variant/20 pb-2">
                  <div className="flex justify-between items-start gap-2">
                    <span className="text-xs font-bold text-on-surface font-mono truncate flex-1" title={v.filename}>
                      {v.filename}
                    </span>
                    <span className="text-[9px] px-1.5 py-0.5 rounded font-bold font-mono uppercase shrink-0 bg-primary/15 text-primary border border-primary/30">
                      {v.layout === 'youtube' ? '16:9 YouTube' : '9:16 Reels'}
                    </span>
                  </div>
                  <span className="text-[10px] text-on-surface-variant font-mono">
                    {formatDate(v.created_at, language)}
                  </span>
                </div>

                {/* Details Grid */}
                <div className="grid grid-cols-2 gap-2 text-[10px] font-mono">
                  <div className="bg-surface-container-lowest p-2 rounded-lg border border-surface-variant/20 flex flex-col gap-0.5">
                    <span className="text-on-surface-variant text-[9px]">{t('common.videos.reciter')}</span>
                    <span className="text-on-surface font-semibold font-sans truncate">{language === 'ar' ? v.reciter : v.reciter_en}</span>
                  </div>
                  <div className="bg-surface-container-lowest p-2 rounded-lg border border-surface-variant/20 flex flex-col gap-0.5">
                    <span className="text-on-surface-variant text-[9px]">{t('common.videos.surah')}</span>
                    <span className="text-on-surface font-semibold">{t('jobs.surahLabel', { number: v.surah })}</span>
                  </div>
                  <div className="bg-surface-container-lowest p-2 rounded-lg border border-surface-variant/20 flex flex-col gap-0.5">
                    <span className="text-on-surface-variant text-[9px]">{t('common.videos.scope')}</span>
                    <span className="text-on-surface font-semibold truncate">{formatScope(v)}</span>
                  </div>
                  <div className="bg-surface-container-lowest p-2 rounded-lg border border-surface-variant/20 flex flex-col gap-0.5">
                    <span className="text-on-surface-variant text-[9px]">{t('common.videos.duration')}</span>
                    <span className="text-secondary font-semibold">{v.duration_human}</span>
                  </div>
                </div>
              </div>

              {/* Actions */}
              <div className="flex gap-2 justify-end border-t border-surface-variant/20 pt-2.5">
                <button
                  onClick={() => setActiveVideo({ filename: v.filename, url: `${api.baseUrl}${v.url}` })}
                  className="bg-surface-container-high hover:bg-surface-bright text-on-surface text-[11px] px-2.5 py-1.5 rounded-lg border border-surface-variant/30 font-semibold cursor-pointer flex-1 flex items-center justify-center gap-1 transition-colors"
                >
                  <span className="material-symbols-outlined text-[15px] text-primary">play_arrow</span>
                  <span>{isAr ? 'معاينة' : 'Play'}</span>
                </button>
                <a
                  href={`${api.baseUrl}${v.url}`}
                  download={v.filename}
                  className="bg-primary hover:bg-tertiary text-on-primary text-[11px] px-2.5 py-1.5 rounded-lg font-bold flex-1 text-center flex items-center justify-center gap-1 shadow-sm transition-all"
                >
                  <span className="material-symbols-outlined text-[15px]">download</span>
                  <span>{isAr ? 'تنزيل' : 'Download'}</span>
                </a>
                <button
                  onClick={() => setDeleteTarget(v)}
                  className="bg-red-500/15 hover:bg-red-500/25 text-red-400 text-[11px] px-2.5 py-1.5 rounded-lg border border-red-500/30 font-semibold cursor-pointer flex items-center justify-center transition-colors"
                  title={isAr ? 'حذف' : 'Delete'}
                >
                  <span className="material-symbols-outlined text-[15px]">delete</span>
                </button>
              </div>
            </div>
          ))}
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
                <span>{t('common.videos.downloadVideoFile')}</span>
              </a>
            </div>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {deleteTarget && (
        <div className="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-surface-container border border-surface-variant/40 max-w-md w-full rounded-2xl p-5 shadow-2xl flex flex-col gap-4">
            <div className="flex items-center gap-3 border-b border-surface-variant/30 pb-3">
              <div className="w-10 h-10 bg-red-500/15 text-red-400 rounded-xl flex items-center justify-center border border-red-500/30">
                <span className="material-symbols-outlined text-[20px]">warning</span>
              </div>
              <div className="flex flex-col">
                <h3 className="font-bold text-on-surface text-sm">{t('common.videos.deleteVideo')}</h3>
                <span className="text-[11px] text-on-surface-variant truncate max-w-xs">{deleteTarget.filename}</span>
              </div>
            </div>

            <div className="bg-surface-container-lowest border border-surface-variant/30 rounded-xl p-3 flex flex-col gap-2 font-sans text-xs">
              <div className="flex justify-between">
                <span className="text-on-surface-variant">{t('common.videos.surah')}</span>
                <span className="font-semibold text-on-surface">{t('jobs.surahLabel', { number: deleteTarget.surah })}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-on-surface-variant">{t('common.videos.reciter')}</span>
                <span className="font-semibold text-on-surface">{language === 'ar' ? deleteTarget.reciter : deleteTarget.reciter_en}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-on-surface-variant">{t('common.videos.scope')}</span>
                <span className="font-semibold text-on-surface">{formatScope(deleteTarget)}</span>
              </div>
            </div>

            <p className="text-xs text-red-400 font-medium">
              {t('common.videos.deleteWarning')}
            </p>

            <div className="flex gap-2 justify-end mt-1">
              <button
                type="button"
                onClick={() => setDeleteTarget(null)}
                disabled={deleting}
                className="bg-surface-container-high hover:bg-surface-bright text-on-surface border border-surface-variant/40 px-4 py-2 rounded-xl text-xs disabled:opacity-50 cursor-pointer font-semibold"
              >
                {t('common.cancel')}
              </button>
              <button
                type="button"
                onClick={handleDelete}
                disabled={deleting}
                className="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-xl text-xs disabled:opacity-50 flex items-center gap-1 cursor-pointer font-bold transition-colors"
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
