'use client';

import React, { useEffect, useState, useMemo } from 'react';
import { useApi } from '@/context/ApiContext';
import { VideoFile } from '@/services/api';

export default function VideosPage() {
  const { api } = useApi();

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
      setErrorMsg('Failed to load completed videos.');
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
      showToast('Video deleted successfully.');
      setDeleteTarget(null);
      // Reload list immediately
      await loadVideos();
    } catch (err: any) {
      console.error('Deletion failed:', err);
      alert(err.message || 'Failed to delete the video.');
    } finally {
      setDeleting(false);
    }
  };

  // Extract unique reciters English names dynamically from videos to build filter options
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
          ? 'full surah السورة كاملة'
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
      return 'Full Surah';
    }
    if (v.from_ayah === v.to_ayah) {
      return `Ayah ${v.from_ayah}`;
    }
    return `Ayahs ${v.from_ayah} - ${v.to_ayah}`;
  };

  const formatDatetime = (dateStr: string) => {
    try {
      const d = new Date(dateStr);
      // Format: 30 Jun 2026 03:25 PM
      const day = d.getDate();
      const month = d.toLocaleString('en-US', { month: 'short' });
      const year = d.getFullYear();
      let hour = d.getHours();
      const min = String(d.getMinutes()).padStart(2, '0');
      const ampm = hour >= 12 ? 'PM' : 'AM';
      hour = hour % 12;
      hour = hour ? hour : 12; // the hour '0' should be '12'
      const formattedHour = String(hour).padStart(2, '0');
      return `${day} ${month} ${year} ${formattedHour}:${min} ${ampm}`;
    } catch (e) {
      return dateStr;
    }
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Title */}
      <div className="border-b border-zinc-900 pb-4">
        <h1 className="text-2xl font-extrabold text-zinc-150 flex items-center justify-between">
          <span>Completed Videos Library</span>
          <span className="text-sm text-zinc-500 font-mono">مكتبة الفيديوهات المكتملة</span>
        </h1>
        <p className="text-xs text-zinc-500 mt-1">
          Access, filter, search and delete rendered Quran video clips stored on the server.
        </p>
      </div>

      {toastMsg && (
        <div className="fixed bottom-6 right-6 z-50 bg-emerald-500 text-black font-bold px-5 py-3 rounded-xl shadow-2xl animate-bounce flex items-center gap-2">
          <span>✓</span>
          <span>{toastMsg}</span>
        </div>
      )}

      {errorMsg && (
        <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl text-sm font-mono">
          {errorMsg}
        </div>
      )}

      {/* Control Bar: Search & Filters */}
      <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-5 flex flex-col gap-4 shadow-2xl">
        {/* Search */}
        <div className="relative">
          <input
            type="text"
            placeholder="Search videos (e.g. 56, علي جابر, yasser, 27, full)..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full bg-zinc-900 border border-zinc-800 text-zinc-200 pl-10 pr-4 py-2.5 rounded-xl focus:outline-none focus:border-zinc-700 transition-colors font-medium text-sm"
          />
          <div className="absolute inset-y-0 left-3 flex items-center pointer-events-none text-zinc-500">
            🔍
          </div>
          {searchTerm && (
            <button
              onClick={() => setSearchTerm('')}
              className="absolute inset-y-0 right-3 flex items-center text-zinc-500 hover:text-zinc-350 text-xs"
            >
              Clear
            </button>
          )}
        </div>

        {/* Filter selectors */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          {/* Reciter Filter */}
          <div className="flex flex-col gap-1.5">
            <label className="text-2xs font-bold text-zinc-500 font-mono">RECITER</label>
            <select
              value={selectedReciter}
              onChange={(e) => setSelectedReciter(e.target.value)}
              className="w-full bg-zinc-900 border border-zinc-800 text-zinc-300 px-3 py-2 rounded-xl text-xs focus:outline-none focus:border-zinc-700 transition-colors"
            >
              <option value="all">All Reciters (الكل)</option>
              {uniqueReciters.map((name) => (
                <option key={name} value={name}>
                  {name}
                </option>
              ))}
            </select>
          </div>

          {/* Scope Filter */}
          <div className="flex flex-col gap-1.5">
            <label className="text-2xs font-bold text-zinc-500 font-mono">SCOPE</label>
            <select
              value={selectedScope}
              onChange={(e) => setSelectedScope(e.target.value)}
              className="w-full bg-zinc-900 border border-zinc-800 text-zinc-300 px-3 py-2 rounded-xl text-xs focus:outline-none focus:border-zinc-700 transition-colors"
            >
              <option value="all">All Scopes (كل المدى)</option>
              <option value="full">Full Surah (السورة كاملة)</option>
              <option value="single">Single Ayah (آية واحدة)</option>
              <option value="range">Ayah Range (مجموعة آيات)</option>
            </select>
          </div>

          {/* Sorting */}
          <div className="flex flex-col gap-1.5">
            <label className="text-2xs font-bold text-zinc-500 font-mono">SORT BY</label>
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value)}
              className="w-full bg-zinc-900 border border-zinc-800 text-zinc-300 px-3 py-2 rounded-xl text-xs focus:outline-none focus:border-zinc-700 transition-colors"
            >
              <option value="newest">Newest First (الأحدث)</option>
              <option value="oldest">Oldest First (الأقدم)</option>
              <option value="surah">Surah Number (رقم السورة)</option>
              <option value="reciter">Reciter Name (القارئ)</option>
              <option value="duration">Duration Length (المدة)</option>
            </select>
          </div>
        </div>
      </div>

      {loading ? (
        <div className="flex flex-col items-center justify-center min-h-[30vh]">
          <div className="w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
          <span className="text-xs text-zinc-500 mt-3 font-mono">Loading videos...</span>
        </div>
      ) : filteredVideos.length === 0 ? (
        <div className="border border-zinc-900 bg-zinc-950/40 rounded-2xl p-12 text-center flex flex-col items-center justify-center gap-3">
          <div className="w-10 h-10 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-400">
            🔎
          </div>
          <h3 className="font-semibold text-zinc-350">No Matching Videos</h3>
          <p className="text-xs text-zinc-500 max-w-sm">
            Adjust your search terms or filter combinations to locate generated files.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filteredVideos.map((v) => (
            <div
              key={v.filename}
              className="bg-zinc-950 border border-zinc-900 rounded-2xl p-5 flex flex-col justify-between gap-4 shadow-xl hover:border-zinc-800 transition-all relative group"
            >
              <div className="flex flex-col gap-2.5">
                {/* Filename Header */}
                <div className="flex flex-col gap-1 border-b border-zinc-900 pb-2">
                  <span className="text-xs font-bold text-zinc-300 font-mono truncate" title={v.filename}>
                    {v.filename}
                  </span>
                  <span className="text-[10px] text-zinc-500 font-mono">
                    {formatDatetime(v.created_at)}
                  </span>
                </div>

                {/* Details */}
                <div className="grid grid-cols-2 gap-2 text-2xs font-mono">
                  <div className="bg-zinc-900/50 p-2 rounded-lg border border-zinc-900/40 flex flex-col gap-0.5">
                    <span className="text-zinc-500 text-3xs">Reciter</span>
                    <span className="text-zinc-300 font-semibold font-sans truncate">{v.reciter}</span>
                  </div>
                  <div className="bg-zinc-900/50 p-2 rounded-lg border border-zinc-900/40 flex flex-col gap-0.5">
                    <span className="text-zinc-500 text-3xs">Surah</span>
                    <span className="text-zinc-300 font-semibold">Surah {v.surah}</span>
                  </div>
                  <div className="bg-zinc-900/50 p-2 rounded-lg border border-zinc-900/40 flex flex-col gap-0.5">
                    <span className="text-zinc-500 text-3xs">Scope</span>
                    <span className="text-zinc-300 font-semibold">{formatScope(v)}</span>
                  </div>
                  <div className="bg-zinc-900/50 p-2 rounded-lg border border-zinc-900/40 flex flex-col gap-0.5">
                    <span className="text-zinc-500 text-3xs">Duration</span>
                    <span className="text-zinc-300 font-semibold">{v.duration_human}</span>
                  </div>
                </div>
              </div>

              {/* Actions */}
              <div className="flex gap-2 justify-end border-t border-zinc-900 pt-3">
                <button
                  onClick={() => setActiveVideo({ filename: v.filename, url: `${api.baseUrl}${v.url}` })}
                  className="bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-2xs px-2.5 py-1.5 rounded-lg border border-emerald-500/20 transition-all font-bold active:scale-95 cursor-pointer flex-1 flex items-center justify-center gap-1"
                >
                  <span>▶</span>
                  <span>Play</span>
                </button>
                <a
                  href={`${api.baseUrl}${v.url}`}
                  download={v.filename}
                  className="bg-zinc-900 hover:bg-zinc-800 text-zinc-300 text-2xs px-2.5 py-1.5 rounded-lg border border-zinc-800 transition-all font-semibold active:scale-95 flex-1 text-center flex items-center justify-center gap-1"
                >
                  <span>⬇</span>
                  <span>Download</span>
                </a>
                <button
                  onClick={() => setDeleteTarget(v)}
                  className="bg-red-500/10 hover:bg-red-500/25 text-red-400 text-2xs px-2.5 py-1.5 rounded-lg border border-red-500/20 transition-all font-semibold active:scale-95 cursor-pointer flex-1 flex items-center justify-center gap-1"
                >
                  <span>🗑</span>
                  <span>Delete</span>
                </button>
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
                Download Video File
              </a>
            </div>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {deleteTarget && (
        <div className="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4 animate-fade-in">
          <div className="bg-zinc-950 border border-zinc-900 max-w-md w-full rounded-2xl p-6 shadow-2xl flex flex-col gap-4">
            <div className="flex items-center gap-3 border-b border-zinc-900 pb-3">
              <div className="w-10 h-10 bg-red-500/10 text-red-500 rounded-xl flex items-center justify-center border border-red-500/20 text-lg">
                ⚠️
              </div>
              <div className="flex flex-col">
                <h3 className="font-bold text-zinc-250">Delete Video</h3>
                <span className="text-[10px] text-zinc-500 font-mono">حذف الفيديو</span>
              </div>
            </div>

            <div className="bg-zinc-900/40 border border-zinc-900 rounded-xl p-4 flex flex-col gap-2 font-sans text-xs">
              <div className="flex justify-between">
                <span className="text-zinc-500">Surah</span>
                <span className="font-semibold text-zinc-300">Surah {deleteTarget.surah}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-zinc-500">Reciter</span>
                <span className="font-semibold text-zinc-300">{deleteTarget.reciter} ({deleteTarget.reciter_en})</span>
              </div>
              <div className="flex justify-between">
                <span className="text-zinc-500">Scope</span>
                <span className="font-semibold text-zinc-300">{formatScope(deleteTarget)}</span>
              </div>
            </div>

            <p className="text-xs text-red-400 font-medium">
              This action is permanent and cannot be undone.
            </p>

            <div className="flex gap-3 justify-end mt-2">
              <button
                type="button"
                onClick={() => setDeleteTarget(null)}
                disabled={deleting}
                className="bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 px-4 py-2 rounded-xl text-xs transition-all active:scale-95 disabled:opacity-50 cursor-pointer font-semibold"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleDelete}
                disabled={deleting}
                className="bg-red-500 hover:bg-red-650 text-white px-4 py-2 rounded-xl text-xs transition-all active:scale-95 disabled:opacity-50 flex items-center gap-1 cursor-pointer font-bold"
              >
                {deleting ? 'Deleting...' : 'Delete'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
