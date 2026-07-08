'use client';

import React, { useState } from 'react';
import { useApi } from '@/context/ApiContext';
import { useLanguage } from '@/hooks/useLanguage';

interface GenerateMushafPromptCardProps {
  surahNumber: number | '';
  fromAyah: number;
  toAyah: number;
  scope: 'full' | 'single' | 'range';
  ayahNumber: number;
  layout: string;
}

export default function GenerateMushafPromptCard({
  surahNumber,
  fromAyah,
  toAyah,
  scope,
  ayahNumber,
  layout,
}: GenerateMushafPromptCardProps) {
  const { api } = useApi();
  const { language } = useLanguage();
  const isAr = language === 'ar';

  const [startPage, setStartPage] = useState<string>('');
  const [startLine, setStartLine] = useState<string>('');
  const [markers, setMarkers] = useState<string>('');
  
  const [prompt, setPrompt] = useState<string>('');
  const [generating, setGenerating] = useState<boolean>(false);
  const [copied, setCopied] = useState<boolean>(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  if (layout !== 'youtube' || surahNumber === '') {
    return null;
  }

  const getAyahParams = () => {
    let from_ayah: number | null = null;
    let to_ayah: number | null = null;
    if (scope === 'single') {
      from_ayah = ayahNumber;
      to_ayah = ayahNumber;
    } else if (scope === 'range') {
      from_ayah = fromAyah;
      to_ayah = toAyah;
    }
    return { from_ayah, to_ayah };
  };

  const handleGenerate = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);
    setPrompt('');

    if (!startPage) {
      setErrorMessage('Start Page is required.');
      return;
    }
    if (!startLine) {
      setErrorMessage('Start Line is required.');
      return;
    }
    if (!markers.trim()) {
      setErrorMessage('Adobe Audition Markers are required.');
      return;
    }

    setGenerating(true);
    const { from_ayah, to_ayah } = getAyahParams();

    try {
      const res = await api.generateMushafPrompt({
        surah: surahNumber,
        from_ayah,
        to_ayah,
        start_page: Number(startPage),
        start_line: Number(startLine),
        markers,
      });
      setPrompt(res.prompt);
    } catch (err: unknown) {
      const error = err as { message?: string };
      setErrorMessage(error.message || 'Failed to generate prompt');
    } finally {
      setGenerating(false);
    }
  };

  const handleCopy = () => {
    navigator.clipboard.writeText(prompt);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4 font-sans">
      <div>
        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          {isAr ? 'أدوات الذكاء الاصطناعي — منشئ مطالبات توقيت المصحف' : 'AI Tools — Mushaf Line Prompt Builder'}
        </h3>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 font-sans">
          {isAr ? 'توليد المطالبات واستخدام مسارات العمل المدعومة بالذكاء الاصطناعي.' : 'Generate prompts and use AI-assisted workflows.'}
        </p>
      </div>

      <form onSubmit={handleGenerate} className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-4">
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
              {isAr ? 'صفحة البدء *' : 'Start Page *'}
            </label>
            <input
              type="number"
              min={1}
              value={startPage}
              onChange={(e) => setStartPage(e.target.value)}
              placeholder="e.g. 578"
              className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 font-mono text-sm"
              required
            />
            <span className="text-[10px] text-slate-400 dark:text-slate-500 font-sans">
              {isAr ? 'مثال: 578' : 'Example: 578'}
            </span>
          </div>

          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
              {isAr ? 'سطر البدء *' : 'Start Line *'}
            </label>
            <input
              type="number"
              min={1}
              max={15}
              value={startLine}
              onChange={(e) => setStartLine(e.target.value)}
              placeholder="e.g. 12"
              className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 font-mono text-sm"
              required
            />
            <span className="text-[10px] text-slate-400 dark:text-slate-500 font-sans">
              {isAr ? 'مثال: 12' : 'Example: 12'}
            </span>
          </div>
        </div>

        <div className="flex flex-col gap-1.5">
          <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
            {isAr ? 'علامات أدوبي أودیشن *' : 'Adobe Audition Markers *'}
          </label>
          <textarea
            rows={4}
            value={markers}
            onChange={(e) => setMarkers(e.target.value)}
            placeholder={"0:00.000\n0:12.034\n0:27.177"}
            className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 font-mono text-xs leading-relaxed"
            required
          />
          <span className="text-[10px] text-slate-400 dark:text-slate-500 font-sans">
            {isAr ? 'الصق علامات Adobe Audition تمامًا كما تم تصديرها.' : 'Paste Adobe Audition markers exactly as exported.'}
          </span>
        </div>

        {errorMessage && (
          <div className="bg-red-50 text-red-750 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900/50 p-3 rounded-xl text-xs font-semibold">
            {errorMessage}
          </div>
        )}

        <button
          type="submit"
          disabled={generating}
          className="w-full bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 dark:hover:bg-slate-200 text-white dark:text-slate-900 font-semibold py-2.5 px-4 rounded-xl text-xs disabled:opacity-40 flex items-center justify-center gap-2 cursor-pointer"
        >
          {generating ? (isAr ? 'جاري التوليد...' : 'Generating Prompt...') : (isAr ? 'توليد المخطط' : 'Generate prompt')}
        </button>
      </form>

      {prompt && (
        <div className="border-t border-slate-100 dark:border-slate-800/60 pt-4 flex flex-col gap-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-700 dark:text-slate-350">
              {isAr ? 'المخطط المولد' : 'Generated Prompt'}
            </span>
            <button
              type="button"
              onClick={handleCopy}
              className={`px-3 py-1 rounded-lg text-xs font-semibold border ${
                copied
                  ? 'bg-emerald-50 border-emerald-250 text-emerald-700 dark:bg-emerald-950/20 dark:border-emerald-900 dark:text-emerald-400'
                  : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-750 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'
              }`}
            >
              {copied ? (isAr ? 'تم النسخ' : 'Copied') : (isAr ? 'نسخ المخطط' : 'Copy prompt')}
            </button>
          </div>
          <textarea
            readOnly
            rows={12}
            value={prompt}
            className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-400 px-3 py-2 rounded-xl focus:outline-none font-mono text-[11px] leading-relaxed resize-y"
          />
        </div>
      )}
    </div>
  );
}
