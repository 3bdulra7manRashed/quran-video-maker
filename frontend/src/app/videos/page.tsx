'use client';

import React, { useEffect, useState } from 'react';
import { useApi } from '@/context/ApiContext';
import { VideoFile } from '@/services/api';

export default function VideosPage() {
  const { api } = useApi();

  const [videos, setVideos] = useState<VideoFile[]>([]);
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Video Modal Player
  const [activeVideo, setActiveVideo] = useState<{ filename: string; url: string } | null>(null);

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

  const formatScope = (v: VideoFile) => {
    if (v.from_ayah === null || v.to_ayah === null) {
      return 'Full Surah';
    }
    if (v.from_ayah === v.to_ayah) {
      return `Ayah ${v.from_ayah}`;
    }
    return `Ayahs ${v.from_ayah} - ${v.to_ayah}`;
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
          Access all successfully rendered video clips stored on the server.
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
          <span className="text-xs text-zinc-500 mt-3 font-mono">Loading videos...</span>
        </div>
      ) : videos.length === 0 ? (
        <div className="border border-zinc-900 bg-zinc-950/40 rounded-2xl p-12 text-center flex flex-col items-center justify-center gap-3">
          <div className="w-10 h-10 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-400">
            🎬
          </div>
          <h3 className="font-semibold text-zinc-350">No Rendered Videos</h3>
          <p className="text-xs text-zinc-500 max-w-sm">
            Generate and complete render jobs from the Console first to see output videos here.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {videos.map((v) => (
            <div
              key={v.filename}
              className="bg-zinc-950 border border-zinc-900 rounded-2xl p-5 flex flex-col justify-between gap-4 shadow-xl hover:border-zinc-800 transition-all"
            >
              <div className="flex flex-col gap-2.5">
                {/* Filename Header */}
                <div className="flex flex-col gap-1 border-b border-zinc-900 pb-2">
                  <span className="text-xs font-bold text-zinc-300 font-mono truncate" title={v.filename}>
                    {v.filename}
                  </span>
                  <span className="text-3xs text-zinc-500 font-mono">
                    {new Date(v.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}
                  </span>
                </div>

                {/* Details */}
                <div className="grid grid-cols-2 gap-2 text-2xs font-mono">
                  <div className="bg-zinc-900/50 p-2 rounded-lg border border-zinc-900/40 flex flex-col gap-0.5">
                    <span className="text-zinc-500">Reciter</span>
                    <span className="text-zinc-300 font-semibold font-sans">{v.reciter}</span>
                  </div>
                  <div className="bg-zinc-900/50 p-2 rounded-lg border border-zinc-900/40 flex flex-col gap-0.5">
                    <span className="text-zinc-500">Surah</span>
                    <span className="text-zinc-300 font-semibold">Surah {v.surah}</span>
                  </div>
                  <div className="bg-zinc-900/50 p-2 rounded-lg border border-zinc-900/40 flex flex-col gap-0.5 col-span-2">
                    <span className="text-zinc-500">Scope</span>
                    <span className="text-zinc-300 font-semibold">{formatScope(v)}</span>
                  </div>
                </div>
              </div>

              {/* Actions */}
              <div className="flex gap-2 justify-end border-t border-zinc-900 pt-3">
                <button
                  onClick={() => setActiveVideo({ filename: v.filename, url: `${api.baseUrl}${v.url}` })}
                  className="bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-xs px-3 py-1.5 rounded-lg border border-emerald-500/30 transition-all font-semibold active:scale-95 cursor-pointer flex-1"
                >
                  Play
                </button>
                <a
                  href={`${api.baseUrl}${v.url}`}
                  download={v.filename}
                  className="bg-zinc-900 hover:bg-zinc-800 text-zinc-200 text-xs px-3 py-1.5 rounded-lg border border-zinc-800 transition-all font-semibold active:scale-95 flex-1 text-center flex items-center justify-center"
                >
                  Download
                </a>
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
    </div>
  );
}
