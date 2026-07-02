'use client';

import React, { useState } from 'react';
import { useApi } from '@/context/ApiContext';
import { useT } from '@/hooks/useT';

interface GenerateContentCardProps {
  surahNumber: number | '';
  reciterSlug: string;
  scope: 'full' | 'single' | 'range';
  ayahNumber: number;
  fromAyah: number;
  toAyah: number;
  layout: string;
}

export default function GenerateContentCard({
  surahNumber,
  reciterSlug,
  scope,
  ayahNumber,
  fromAyah,
  toAyah,
  layout,
}: GenerateContentCardProps) {
  const { api } = useApi();
  const t = useT();

  const [prompt, setPrompt] = useState<string>('');
  const [copied, setCopied] = useState<boolean>(false);
  const [jsonInput, setJsonInput] = useState<string>('');
  const [generatorType, setGeneratorType] = useState<string>('gemini');
  const [generatorModel, setGeneratorModel] = useState<string>('gemini-2.5-pro');
  const [latencyMs, setLatencyMs] = useState<string>('');
  
  const [validating, setValidating] = useState<boolean>(false);
  const [saving, setSaving] = useState<boolean>(false);
  
  const [errors, setErrors] = useState<string[]>([]);
  const [warnings, setWarnings] = useState<string[]>([]);
  const [segments, setSegments] = useState<Array<{ order: number; arabic: string; translation: string; tafsir: string }>>([]);
  const [isValid, setIsValid] = useState<boolean | null>(null);
  
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  if (layout !== 'reels' || surahNumber === '' || !reciterSlug) {
    return null;
  }

  // Resolve scope constraints for backend
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

  const handleGeneratePrompt = async () => {
    setStatusMessage(null);
    setErrorMessage(null);
    const { from_ayah, to_ayah } = getAyahParams();
    try {
      const res = await api.generatePrompt({
        reciter: reciterSlug,
        surah: surahNumber,
        from_ayah,
        to_ayah,
      });
      setPrompt(res.prompt);
    } catch (err: any) {
      setErrorMessage(err.message || 'Failed to generate prompt');
    }
  };

  const handleCopyPrompt = () => {
    navigator.clipboard.writeText(prompt);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleValidate = async () => {
    setStatusMessage(null);
    setErrorMessage(null);
    setIsValid(null);
    setErrors([]);
    setWarnings([]);
    setSegments([]);

    if (!jsonInput.trim()) {
      setErrors(['JSON input cannot be empty.']);
      setIsValid(false);
      return;
    }

    setValidating(true);
    const { from_ayah, to_ayah } = getAyahParams();

    try {
      const res = await api.previewImport({
        json: jsonInput,
        surah: surahNumber,
        from_ayah,
        to_ayah,
      });

      setIsValid(res.isValid);
      setErrors(res.errors || []);
      setWarnings(res.warnings || []);
      if (res.segments) {
        setSegments(res.segments);
      }
    } catch (err: any) {
      setErrors([err.message || 'Validation request failed.']);
      setIsValid(false);
    } finally {
      setValidating(false);
    }
  };

  const handleApprove = async () => {
    setStatusMessage(null);
    setErrorMessage(null);

    if (isValid !== true) {
      setErrorMessage('Please validate the JSON successfully before approving.');
      return;
    }

    setSaving(true);
    const { from_ayah, to_ayah } = getAyahParams();
    const parsedLatency = latencyMs ? parseInt(latencyMs, 10) : null;

    try {
      const res = await api.approveImport({
        json: jsonInput,
        reciter: reciterSlug,
        surah: surahNumber,
        from_ayah,
        to_ayah,
        generator_type: generatorType,
        generator_model: generatorModel || undefined,
        generator_latency_ms: isNaN(Number(parsedLatency)) ? null : parsedLatency,
        prompt_version: 'v1',
      });

      setStatusMessage(t('common.contentPipeline.saveSuccess', { version: String(res.version) }));
    } catch (err: any) {
      setErrorMessage(err.message || 'Failed to approve and save content.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 flex flex-col gap-5 shadow-2xl relative overflow-hidden transition-all duration-300">
      <div className="absolute top-0 end-0 w-32 h-32 bg-indigo-500/5 blur-3xl pointer-events-none rounded-full"></div>

      <div className="flex items-center justify-between border-b border-zinc-900 pb-3">
        <h3 className="font-semibold text-zinc-200 font-sans flex items-center gap-2">
          <span>🧠</span>
          <span>{t('common.contentPipeline.title')}</span>
          <span className="text-[9px] bg-zinc-800 text-zinc-400 border border-zinc-700 px-2 py-0.5 rounded-full font-mono uppercase tracking-wider">
            Reels Layout v1.0
          </span>
        </h3>
      </div>

      {/* STEP 1: PROMPT GENERATION */}
      <div className="flex flex-col gap-2">
        <div className="flex justify-between items-center">
          <label className="text-xs font-semibold text-zinc-400 uppercase tracking-wider">
            1. {t('common.contentPipeline.generatePrompt')}
          </label>
        </div>
        
        <div className="flex gap-2">
          <button
            type="button"
            onClick={handleGeneratePrompt}
            className="flex-1 bg-zinc-900 hover:bg-zinc-850 text-zinc-200 border border-zinc-800 text-xs font-semibold py-2.5 px-4 rounded-xl transition-all duration-200 active:scale-98 cursor-pointer text-center"
          >
            💬 Generate Segment Prompt
          </button>
          
          {prompt && (
            <button
              type="button"
              onClick={handleCopyPrompt}
              className="bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 text-xs font-bold py-2.5 px-4 rounded-xl transition-all duration-200 active:scale-98 cursor-pointer"
            >
              {copied ? `✅ ${t('common.contentPipeline.copied')}` : `📋 ${t('common.contentPipeline.copyPrompt')}`}
            </button>
          )}
        </div>

        {prompt && (
          <textarea
            readOnly
            value={prompt}
            className="w-full h-32 bg-zinc-900/50 border border-zinc-900 rounded-xl p-3 text-[10px] font-mono text-zinc-400 focus:outline-none focus:border-zinc-800 resize-none mt-2"
          />
        )}
      </div>

      {/* STEP 2: METADATA & PASTE JSON */}
      <div className="flex flex-col gap-3 border-t border-zinc-900 pt-4">
        <label className="text-xs font-semibold text-zinc-400 uppercase tracking-wider">
          2. Paste Generated Segment JSON
        </label>

        {/* METADATA INPUTS */}
        <div className="grid grid-cols-3 gap-2">
          <div className="flex flex-col gap-1">
            <span className="text-[10px] text-zinc-500 font-medium">{t('common.contentPipeline.generatorType')}</span>
            <select
              value={generatorType}
              onChange={(e) => setGeneratorType(e.target.value)}
              className="bg-zinc-900 border border-zinc-850 text-zinc-300 rounded-lg p-1.5 text-xs focus:outline-none focus:border-zinc-800"
            >
              <option value="gemini">Gemini</option>
              <option value="openai">OpenAI</option>
              <option value="claude">Claude</option>
              <option value="manual">Manual</option>
            </select>
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-[10px] text-zinc-500 font-medium">Model</span>
            <input
              type="text"
              value={generatorModel}
              onChange={(e) => setGeneratorModel(e.target.value)}
              placeholder="gemini-2.5-pro"
              className="bg-zinc-900 border border-zinc-850 text-zinc-300 rounded-lg p-1.5 text-xs focus:outline-none focus:border-zinc-800 font-mono"
            />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-[10px] text-zinc-500 font-medium">Latency (ms)</span>
            <input
              type="number"
              value={latencyMs}
              onChange={(e) => setLatencyMs(e.target.value)}
              placeholder="e.g. 1500"
              className="bg-zinc-900 border border-zinc-850 text-zinc-300 rounded-lg p-1.5 text-xs focus:outline-none focus:border-zinc-800 font-mono"
            />
          </div>
        </div>

        <textarea
          value={jsonInput}
          onChange={(e) => {
            setJsonInput(e.target.value);
            setIsValid(null); // Reset validation state on change
          }}
          placeholder={t('common.contentPipeline.pasteJsonPlaceholder')}
          className="w-full h-32 bg-zinc-900/30 border border-zinc-900 focus:border-zinc-800 rounded-xl p-3 text-xs font-mono text-zinc-300 focus:outline-none resize-y"
        />

        <button
          type="button"
          onClick={handleValidate}
          disabled={validating || !jsonInput.trim()}
          className="w-full bg-zinc-900 hover:bg-zinc-850 text-zinc-200 border border-zinc-800 text-xs font-semibold py-2.5 px-4 rounded-xl transition-all duration-200 active:scale-98 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer text-center"
        >
          {validating ? (
            <div className="flex items-center justify-center gap-2">
              <div className="w-3.5 h-3.5 border-2 border-zinc-400 border-t-transparent rounded-full animate-spin"></div>
              <span>{t('common.contentPipeline.validating')}</span>
            </div>
          ) : (
            '🔍 Validate & Preview Segments'
          )}
        </button>
      </div>

      {/* STEP 3: ERRORS, WARNINGS & PREVIEW */}
      {(errors.length > 0 || warnings.length > 0 || segments.length > 0 || isValid !== null) && (
        <div className="flex flex-col gap-3 border-t border-zinc-900 pt-4 max-h-[360px] overflow-y-auto pr-1">
          {/* VALIDATION STATUS BANNER */}
          {isValid !== null && (
            <div className={`p-3 rounded-xl border text-xs font-medium flex items-center gap-2 ${
              isValid 
                ? 'bg-emerald-500/5 border-emerald-500/25 text-emerald-400' 
                : 'bg-red-500/5 border-red-500/25 text-red-400'
            }`}>
              <span>{isValid ? '✅ JSON is Valid & Matches Official Scriptures Verbatim' : '❌ Validation Errors Found'}</span>
            </div>
          )}

          {/* ERRORS PANEL */}
          {errors.length > 0 && (
            <div className="bg-red-500/10 border border-red-500/20 text-red-400 p-3 rounded-xl flex flex-col gap-1">
              <span className="text-xs font-bold flex items-center gap-1">
                <span>⚠️</span> <span>{t('common.contentPipeline.errorsFound')} ({errors.length})</span>
              </span>
              <ul className="list-disc list-inside text-[10px] font-mono leading-relaxed mt-1">
                {errors.map((err, i) => (
                  <li key={i}>{err}</li>
                ))}
              </ul>
            </div>
          )}

          {/* WARNINGS PANEL */}
          {warnings.length > 0 && (
            <div className="bg-amber-500/10 border border-amber-500/20 text-amber-400 p-3 rounded-xl flex flex-col gap-1">
              <span className="text-xs font-bold flex items-center gap-1">
                <span>⚠️</span> <span>{t('common.contentPipeline.warningsFound')} ({warnings.length})</span>
              </span>
              <ul className="list-disc list-inside text-[10px] font-mono leading-relaxed mt-1">
                {warnings.map((warn, i) => (
                  <li key={i}>{warn}</li>
                ))}
              </ul>
            </div>
          )}

          {/* PREVIEW TABLE */}
          {segments.length > 0 && (
            <div className="flex flex-col gap-2">
              <span className="text-xs font-bold text-zinc-400">Preview Segment List</span>
              <div className="border border-zinc-900 rounded-xl overflow-hidden text-xs">
                <table className="w-full text-start border-collapse">
                  <thead>
                    <tr className="bg-zinc-900/60 text-zinc-400 border-b border-zinc-900 text-[10px] font-semibold uppercase tracking-wider">
                      <th className="p-2 border-e border-zinc-900 text-center w-12">#</th>
                      <th className="p-2 border-e border-zinc-900 text-end">Arabic Text</th>
                      <th className="p-2 border-e border-zinc-900">Translation</th>
                      <th className="p-2">Tafsir</th>
                    </tr>
                  </thead>
                  <tbody>
                    {segments.map((seg) => (
                      <tr key={seg.order} className="border-b border-zinc-900 last:border-0 hover:bg-zinc-900/20">
                        <td className="p-2 border-e border-zinc-900 text-center font-mono font-bold text-zinc-500">
                          {seg.order}
                        </td>
                        <td className="p-2 border-e border-zinc-900 text-end font-sans font-semibold text-emerald-400 text-sm dir-rtl">
                          {seg.arabic}
                        </td>
                        <td className="p-2 border-e border-zinc-900 text-zinc-300 leading-relaxed text-[11px]">
                          {seg.translation}
                        </td>
                        <td className="p-2 text-zinc-400 leading-relaxed text-[11px]">
                          {seg.tafsir}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      )}

      {/* STEP 4: APPROVAL */}
      {isValid === true && (
        <div className="flex flex-col gap-3 border-t border-zinc-900 pt-4">
          <button
            type="button"
            onClick={handleApprove}
            disabled={saving}
            className="w-full bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 disabled:bg-emerald-500 text-black font-bold py-3 px-4 rounded-xl shadow-[0_0_12px_rgba(16,185,129,0.25)] hover:shadow-[0_0_20px_rgba(16,185,129,0.45)] transition-all duration-200 active:scale-98 cursor-pointer text-center text-xs"
          >
            {saving ? (
              <div className="flex items-center justify-center gap-2">
                <div className="w-3.5 h-3.5 border-2 border-black border-t-transparent rounded-full animate-spin"></div>
                <span>{t('common.contentPipeline.saving')}</span>
              </div>
            ) : (
              `✅ ${t('common.contentPipeline.approveSave')}`
            )}
          </button>
        </div>
      )}

      {/* FEEDBACK TOAST MESSAGES */}
      {statusMessage && (
        <div className="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-3 rounded-xl text-xs font-semibold leading-relaxed">
          {statusMessage}
        </div>
      )}

      {errorMessage && (
        <div className="bg-red-500/10 border border-red-500/20 text-red-400 p-3 rounded-xl text-xs font-mono leading-relaxed">
          {errorMessage}
        </div>
      )}
    </div>
  );
}
