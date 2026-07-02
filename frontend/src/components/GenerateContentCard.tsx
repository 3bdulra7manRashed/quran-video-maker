'use client';

import React, { useState } from 'react';
import { useApi } from '@/context/ApiContext';
import { useT } from '@/hooks/useT';
import { useLanguage } from '@/hooks/useLanguage';

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
  const { language } = useLanguage();
  const isAr = language === 'ar';

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
  const [isRepaired, setIsRepaired] = useState<boolean>(false);

  if (layout !== 'reels' || surahNumber === '' || !reciterSlug) {
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
    setIsRepaired(false);

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
      if (res.repairedJson) {
        setJsonInput(res.repairedJson);
        setIsRepaired(true);
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
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4 font-sans">
      <div>
        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          {isAr ? 'أدوات الذكاء الاصطناعي — منشئ ريلز' : 'AI Tools — Reels Generator'}
        </h3>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 font-sans">
          {isAr ? 'توليد المطالبات واستخدام مسارات العمل المدعومة بالذكاء الاصطناعي.' : 'Generate prompts and use AI-assisted workflows.'}
        </p>
      </div>

      {/* STEP 1: PROMPT GENERATION */}
      <div className="flex flex-col gap-2">
        <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
          {isAr ? '1. توليد مطالبات الأجزاء' : '1. Generate Segment Prompt'}
        </label>
        
        <div className="flex gap-2">
          <button
            type="button"
            onClick={handleGeneratePrompt}
            className="flex-1 bg-slate-900 dark:bg-slate-100 hover:bg-slate-850 dark:hover:bg-slate-200 text-white dark:text-slate-900 font-semibold py-2 px-4 rounded-xl text-xs cursor-pointer text-center"
          >
            {isAr ? 'توليد المخطط' : 'Generate prompt'}
          </button>
          
          {prompt && (
            <button
              type="button"
              onClick={handleCopyPrompt}
              className="bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 font-semibold py-2 px-4 rounded-xl text-xs cursor-pointer"
            >
              {copied ? (isAr ? 'تم النسخ' : 'Copied') : (isAr ? 'نسخ المخطط' : 'Copy prompt')}
            </button>
          )}
        </div>

        {prompt && (
          <textarea
            readOnly
            value={prompt}
            className="w-full h-32 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 text-[10px] font-mono text-slate-700 dark:text-slate-400 focus:outline-none resize-none mt-2"
          />
        )}
      </div>

      {/* STEP 2: METADATA & PASTE JSON */}
      <div className="flex flex-col gap-3 border-t border-slate-100 dark:border-slate-800/60 pt-4">
        <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
          {isAr ? '2. لصق كود JSON للأجزاء المولد' : '2. Paste Generated Segment JSON'}
        </label>

        {/* METADATA INPUTS */}
        <div className="grid grid-cols-3 gap-2">
          <div className="flex flex-col gap-1">
            <span className="text-[10px] text-slate-500 dark:text-slate-400 font-medium">{isAr ? 'النوع' : 'Type'}</span>
            <select
              value={generatorType}
              onChange={(e) => setGeneratorType(e.target.value)}
              className="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 rounded-lg p-1.5 text-xs focus:outline-none focus:border-slate-450 dark:focus:border-slate-700"
            >
              <option value="gemini">Gemini</option>
              <option value="openai">OpenAI</option>
              <option value="claude">Claude</option>
              <option value="manual">Manual</option>
            </select>
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-[10px] text-slate-500 dark:text-slate-400 font-medium">{isAr ? 'النموذج' : 'Model'}</span>
            <input
              type="text"
              value={generatorModel}
              onChange={(e) => setGeneratorModel(e.target.value)}
              placeholder="gemini-2.5-pro"
              className="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 rounded-lg p-1.5 text-xs focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 font-mono"
            />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-[10px] text-slate-500 dark:text-slate-400 font-medium">{isAr ? 'الاستجابة (ملي ثانية)' : 'Latency (ms)'}</span>
            <input
              type="number"
              value={latencyMs}
              onChange={(e) => setLatencyMs(e.target.value)}
              placeholder="e.g. 1500"
              className="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 rounded-lg p-1.5 text-xs focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 font-mono"
            />
          </div>
        </div>

        <textarea
          value={jsonInput}
          onChange={(e) => {
            setJsonInput(e.target.value);
            setIsValid(null);
            setIsRepaired(false);
          }}
          placeholder={isAr ? 'الصق استجابة JSON المستلمة من الذكاء الاصطناعي مباشرة هنا...' : 'Paste the JSON returned by the AI directly here...'}
          className="w-full h-32 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 rounded-xl p-3 text-xs font-mono focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 resize-y"
        />

        <button
          type="button"
          onClick={handleValidate}
          disabled={validating || !jsonInput.trim()}
          className="w-full bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 dark:hover:bg-slate-200 text-white dark:text-slate-900 font-semibold py-2.5 px-4 rounded-xl text-xs disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer text-center"
        >
          {validating ? (
            <span>{isAr ? 'جاري التحقق...' : 'Validating...'}</span>
          ) : (
            isAr ? 'التحقق ومعاينة الأجزاء' : 'Validate & Preview Segments'
          )}
        </button>
      </div>

      {/* STEP 3: ERRORS, WARNINGS & PREVIEW */}
      {(errors.length > 0 || warnings.length > 0 || segments.length > 0 || isValid !== null) && (
        <div className="flex flex-col gap-3 border-t border-slate-100 dark:border-slate-800/60 pt-4 max-h-[360px] overflow-y-auto pr-1">
          {/* VALIDATION STATUS BANNER */}
          {isValid !== null && (
            <div className={`p-3 rounded-xl border text-xs font-medium flex items-center gap-2 ${
              isValid 
                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900' 
                : 'bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400 border-red-200 dark:border-red-900'
            }`}>
              <span>{isValid ? (isAr ? '✅ كود JSON صالح ويتطابق تمامًا مع النص القرآني' : '✅ JSON is Valid & Matches Official Scriptures Verbatim') : (isAr ? '❌ تم العثور على أخطاء أثناء التحقق' : '❌ Validation Errors Found')}</span>
            </div>
          )}

          {/* REPAIRED WARNING BANNER */}
          {isRepaired && (
            <div className="bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400 border border-amber-200 dark:border-amber-900 p-3 rounded-xl flex flex-col gap-1 text-xs">
              <span className="font-bold">{isAr ? '⚠️ تنبيه' : '⚠️ Warning'}</span>
              <p className="text-[11px] leading-relaxed">
                {isAr ? 'تحتوي استجابة الذكاء الاصطناعي على علامات اقتباس غير صالحة. تم إصلاح كود JSON تلقائيًا قبل التحقق.' : 'AI response contained malformed quotation marks. The JSON was repaired automatically before validation.'}
              </p>
            </div>
          )}

          {/* ERRORS PANEL */}
          {errors.length > 0 && (
            <div className="bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900 p-3 rounded-xl flex flex-col gap-1 text-xs">
              <span className="font-bold">⚠️ {t('common.contentPipeline.errorsFound')} ({errors.length})</span>
              <ul className="list-disc list-inside text-[10px] font-mono leading-relaxed mt-1">
                {errors.map((err, i) => (
                  <li key={i}>{err}</li>
                ))}
              </ul>
            </div>
          )}

          {/* WARNINGS PANEL */}
          {warnings.length > 0 && (
            <div className="bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400 border border-amber-200 dark:border-amber-900 p-3 rounded-xl flex flex-col gap-1 text-xs">
              <span className="font-bold">⚠️ {t('common.contentPipeline.warningsFound')} ({warnings.length})</span>
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
              <span className="text-xs font-bold text-slate-500 dark:text-slate-400">
                {isAr ? 'معاينة قائمة الأجزاء' : 'Preview Segment List'}
              </span>
              <div className="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden text-xs bg-white dark:bg-slate-900">
                <table className="w-full text-start border-collapse">
                  <thead>
                    <tr className="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 text-[10px] font-semibold uppercase tracking-wider">
                      <th className="p-2 border-e border-slate-200 dark:border-slate-800 text-center w-12">#</th>
                      <th className="p-2 border-e border-slate-200 dark:border-slate-800 text-end">{isAr ? 'النص العربي' : 'Arabic Text'}</th>
                      <th className="p-2 border-e border-slate-200 dark:border-slate-800">{isAr ? 'الترجمة' : 'Translation'}</th>
                      <th className="p-2">{isAr ? 'التفسير' : 'Tafsir'}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {segments.map((seg) => (
                      <tr key={seg.order} className="border-b border-slate-200 dark:border-slate-800 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-950/50">
                        <td className="p-2 border-e border-slate-200 dark:border-slate-800 text-center font-mono font-bold text-slate-400 dark:text-slate-500">
                          {seg.order}
                        </td>
                        <td className="p-2 border-e border-slate-200 dark:border-slate-800 text-end font-sans font-semibold text-emerald-600 dark:text-emerald-400 text-sm dir-rtl">
                          {seg.arabic}
                        </td>
                        <td className="p-2 border-e border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 leading-relaxed text-[11px]">
                          {seg.translation}
                        </td>
                        <td className="p-2 text-slate-500 dark:text-slate-450 leading-relaxed text-[11px]">
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
        <div className="flex flex-col gap-3 border-t border-slate-100 dark:border-slate-800/60 pt-4">
          <button
            type="button"
            onClick={handleApprove}
            disabled={saving}
            className="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 disabled:bg-emerald-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs cursor-pointer text-center"
          >
            {saving ? (
              <span>{isAr ? 'جاري الحفظ...' : 'Saving...'}</span>
            ) : (
              isAr ? 'حفظ المحتوى المعتمد' : 'Save Approved Content'
            )}
          </button>
        </div>
      )}

      {/* FEEDBACK TOAST MESSAGES */}
      {statusMessage && (
        <div className="bg-emerald-50 text-emerald-750 dark:bg-emerald-950/20 dark:text-emerald-400 border border-emerald-250 dark:border-emerald-900 p-3 rounded-xl text-xs font-semibold leading-relaxed mt-3">
          {statusMessage}
        </div>
      )}

      {errorMessage && (
        <div className="bg-red-50 text-red-750 dark:bg-red-950/20 dark:text-red-400 border border-red-250 dark:border-red-900 p-3 rounded-xl text-xs font-mono leading-relaxed mt-3">
          {errorMessage}
        </div>
      )}
    </div>
  );
}
