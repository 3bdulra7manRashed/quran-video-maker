'use client';

import React, { useRef, useState } from 'react';
import { useLanguage } from '@/hooks/useLanguage';
import SegmentTimingRecorder from './SegmentTimingRecorder';

interface AITaskCardProps {
  title: string;
  description: string;
  icon: string;
  
  // Custom input states and handlers
  requiresMarkers: boolean;
  requiresMushafLineInfo: boolean;
  startPage: string;
  startLine: string;
  markers: string;
  onStartPageChange?: (val: string) => void;
  onStartLineChange?: (val: string) => void;
  onMarkersChange?: (val: string) => void;

  // Prompt generation states and handlers
  generatingPrompt: boolean;
  generatedPrompt: string;
  isCopied: boolean;
  onGeneratePrompt: () => void;
  onCopyPrompt: () => void;

  // JSON input and validation states and handlers
  jsonInput: string;
  onJsonInputChange: (val: string) => void;
  isValidating: boolean;
  onValidateJson: (json?: string) => void;
  validationSuccessMessage: string | null;
  validationErrors: string[];
  validationWarnings: string[];
  errorMessage: string | null;

  // Segmentation-only preview and approval states and handlers
  isSegmentation: boolean;
  segmentsPreview?: Array<{ order: number; arabic: string; translation: string; tafsir: string }>;
  isRepaired?: boolean;
  saving?: boolean;
  generatorType?: string;
  generatorModel?: string;
  latencyMs?: string;
  onGeneratorTypeChange?: (val: string) => void;
  onGeneratorModelChange?: (val: string) => void;
  onLatencyMsChange?: (val: string) => void;
  onApproveJson?: () => void;

  // Timing Recorder custom props
  isSegmentTiming?: boolean;
  surahNumber?: number;
  reciterSlug?: string;
  scope?: 'full' | 'single' | 'range';
  ayahNumber?: number | null;
  fromAyah?: number | null;
  toAyah?: number | null;
  approvedSegments?: Array<{ order: number; arabic: string; translation: string; tafsir: string }>;
  persistToDatabase?: boolean;
  onPersistToDatabaseChange?: (val: boolean) => void;
  onManualTimingsComplete?: (json: string, persistToDatabase?: boolean) => Promise<void>;
}

export default function AITaskCard({
  title,
  description,
  icon,
  
  requiresMarkers,
  requiresMushafLineInfo,
  startPage,
  startLine,
  markers,
  onStartPageChange,
  onStartLineChange,
  onMarkersChange,

  generatingPrompt,
  generatedPrompt,
  isCopied,
  onGeneratePrompt,
  onCopyPrompt,

  jsonInput,
  onJsonInputChange,
  isValidating,
  onValidateJson,
  validationSuccessMessage,
  validationErrors,
  validationWarnings,
  errorMessage,

  isSegmentation,
  segmentsPreview,
  isRepaired,
  saving,
  generatorType,
  generatorModel,
  latencyMs,
  onGeneratorTypeChange,
  onGeneratorModelChange,
  onLatencyMsChange,
  onApproveJson,

  isSegmentTiming,
  surahNumber,
  reciterSlug,
  scope,
  ayahNumber,
  fromAyah,
  toAyah,
  approvedSegments,
  persistToDatabase,
  onPersistToDatabaseChange,
  onManualTimingsComplete,
}: AITaskCardProps) {
  const { language } = useLanguage();
  const isAr = language === 'ar';
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isRecording, setIsRecording] = useState<boolean>(false);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (event) => {
      onJsonInputChange(event.target?.result as string || '');
    };
    reader.readAsText(file);
  };

  if (isSegmentTiming) {
    if (isRecording) {
      return (
        <SegmentTimingRecorder
          surahNumber={surahNumber!}
          reciterSlug={reciterSlug!}
          scope={scope!}
          ayahNumber={ayahNumber ?? null}
          fromAyah={fromAyah ?? null}
          toAyah={toAyah ?? null}
          approvedSegments={approvedSegments || []}
          persistToDatabase={persistToDatabase}
          onPersistToDatabaseChange={onPersistToDatabaseChange}
          onRecordingComplete={async (json, persist) => {
            await onManualTimingsComplete?.(json, persist);
          }}
          onCancel={() => setIsRecording(false)}
        />
      );
    }

    return (
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4 font-sans">
        <div>
          <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <span>{icon}</span>
            <span>{title}</span>
          </h3>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 font-sans">
            {description}
          </p>
        </div>

        {approvedSegments && approvedSegments.length > 0 ? (
          <div className="flex flex-col gap-3 border-t border-slate-100 dark:border-slate-800/60 pt-4">
            <button
              type="button"
              onClick={() => setIsRecording(true)}
              className="w-full bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 dark:hover:bg-slate-200 text-white dark:text-slate-900 font-semibold py-2.5 px-4 rounded-xl text-xs flex items-center justify-center gap-2 cursor-pointer"
            >
              🎙️ Open Segment Timing Recorder
            </button>

            {validationSuccessMessage && (
              <div className="bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border border-emerald-250 dark:border-emerald-900 p-3 rounded-xl text-xs font-semibold font-mono">
                {validationSuccessMessage}
              </div>
            )}
            {errorMessage && (
              <div className="bg-red-50 text-red-750 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900/50 p-3 rounded-xl text-xs font-semibold">
                {errorMessage}
              </div>
            )}

            {jsonInput && (
              <div className="flex flex-col gap-2 pt-2 border-t border-slate-100 dark:border-slate-800/60">
                <button
                  type="button"
                  onClick={() => onValidateJson?.(jsonInput)}
                  disabled={isValidating}
                  className="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold py-2.5 px-4 rounded-xl text-xs flex items-center justify-center gap-2 cursor-pointer transition shadow-md shadow-emerald-950/20"
                >
                  {isValidating ? (
                    <span>⏳ {isAr ? 'جاري الحفظ والتثبيت...' : 'Saving & Persisting...'}</span>
                  ) : (
                    <span>💾 {isAr ? 'حفظ وتثبيت التوقيت في قاعدة البيانات' : 'Save & Persist to Database'}</span>
                  )}
                </button>

                <details className="mt-1">
                  <summary className="text-xs text-slate-400 cursor-pointer select-none hover:text-slate-300">
                    {isAr ? 'عرض وتعديل صياغة JSON للتوقيت' : 'View & Edit Timing JSON'}
                  </summary>
                  <div className="mt-2 flex flex-col gap-2">
                    <textarea
                      rows={6}
                      value={jsonInput}
                      onChange={(e) => onJsonInputChange?.(e.target.value)}
                      className="w-full bg-slate-950 border border-slate-800 text-slate-300 p-3 rounded-xl font-mono text-xs focus:outline-none focus:border-emerald-500"
                    />
                    <button
                      type="button"
                      onClick={() => onValidateJson?.(jsonInput)}
                      disabled={isValidating}
                      className="self-end bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold py-1.5 px-3 rounded-lg cursor-pointer"
                    >
                      {isAr ? 'حفظ واستيراد' : 'Save & Import'}
                    </button>
                  </div>
                </details>
              </div>
            )}
          </div>
        ) : (
          <div className="border-t border-slate-100 dark:border-slate-800/60 pt-4 flex flex-col gap-3">
            <div className="bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50 p-4 rounded-xl text-xs flex flex-col gap-2 font-mono">
              <span className="font-bold">⚠️ Warning:</span>
              <span>No approved segment definitions found. Please generate and approve segments first.</span>
            </div>
          </div>
        )}
      </div>
    );
  }

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4 font-sans">
      {/* Header */}
      <div>
        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <span>{icon}</span>
          <span>{title}</span>
        </h3>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 font-sans">
          {description}
        </p>
      </div>

      {/* Inputs Section */}
      {(requiresMarkers || requiresMushafLineInfo) && (
        <div className="flex flex-col gap-4 border-t border-slate-100 dark:border-slate-800/60 pt-4">
          {requiresMushafLineInfo && (
            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
                  {isAr ? 'صفحة البدء *' : 'Start Page *'}
                </label>
                <input
                  type="number"
                  min={1}
                  value={startPage}
                  onChange={(e) => onStartPageChange?.(e.target.value)}
                  placeholder="e.g. 578"
                  className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 font-mono text-sm"
                  required
                />
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
                  onChange={(e) => onStartLineChange?.(e.target.value)}
                  placeholder="e.g. 12"
                  className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 font-mono text-sm"
                  required
                />
              </div>
            </div>
          )}

          {requiresMarkers && (
            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
                {isAr ? 'علامات أدوبي أودیشن *' : 'Adobe Audition Markers *'}
              </label>
              <textarea
                rows={4}
                value={markers}
                onChange={(e) => onMarkersChange?.(e.target.value)}
                placeholder={"0:00.000\n0:12.034\n0:27.177"}
                className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 font-mono text-xs leading-relaxed"
                required
              />
              <span className="text-[10px] text-slate-400 dark:text-slate-500 font-sans">
                {isAr ? 'الصق علامات Adobe Audition تمامًا كما تم تصديرها.' : 'Paste Adobe Audition markers exactly as exported.'}
              </span>
            </div>
          )}
        </div>
      )}

      {/* Actions: Generate Prompt */}
      <div className="flex flex-col gap-2">
        <button
          type="button"
          onClick={onGeneratePrompt}
          disabled={generatingPrompt}
          className="w-full bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 dark:hover:bg-slate-200 text-white dark:text-slate-900 font-semibold py-2.5 px-4 rounded-xl text-xs disabled:opacity-40 flex items-center justify-center gap-2 cursor-pointer"
        >
          {generatingPrompt ? (isAr ? 'جاري التوليد...' : 'Generating Prompt...') : (isAr ? 'توليد موجه الذكاء الاصطناعي' : 'Generate AI Prompt')}
        </button>
      </div>

      {/* Prompt Block */}
      {generatedPrompt && (
        <div className="border-t border-slate-100 dark:border-slate-800/60 pt-4 flex flex-col gap-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-700 dark:text-slate-350">
              {isAr ? 'الموجه المولد' : 'Generated Prompt'}
            </span>
            <button
              type="button"
              onClick={onCopyPrompt}
              className={`px-3 py-1 rounded-lg text-xs font-semibold border ${
                isCopied
                  ? 'bg-emerald-50 border-emerald-250 text-emerald-700 dark:bg-emerald-950/20 dark:border-emerald-900 dark:text-emerald-400'
                  : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-750 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700'
              }`}
            >
              {isCopied ? (isAr ? 'تم النسخ' : 'Copied') : (isAr ? 'نسخ الموجه' : 'Copy prompt')}
            </button>
          </div>
          <textarea
            readOnly
            rows={8}
            value={generatedPrompt}
            className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-400 px-3 py-2 rounded-xl focus:outline-none font-mono text-[11px] leading-relaxed resize-y"
          />
        </div>
      )}

      {/* JSON Payload Input Block */}
      {generatedPrompt && (
        <div className="border-t border-slate-100 dark:border-slate-800/60 pt-4 flex flex-col gap-4">
          <div>
            <h4 className="text-xs font-semibold text-slate-700 dark:text-slate-350">
              {isAr ? 'استجابة الذكاء الاصطناعي (JSON)' : 'AI Response JSON'}
            </h4>
            <p className="text-[10px] text-slate-400 dark:text-slate-500">
              {isAr ? 'الصق كود JSON الناتج من الذكاء الاصطناعي أو ارفع ملف.' : 'Paste the JSON returned by the AI or upload it as a file.'}
            </p>
          </div>

          <div className="flex flex-col gap-3">
            {/* File Upload Trigger */}
            <div className="flex items-center gap-2">
              <input
                type="file"
                accept=".json"
                ref={fileInputRef}
                onChange={handleFileChange}
                className="hidden"
              />
              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                className="flex-1 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-350 text-xs font-semibold px-3 py-2.5 rounded-xl text-left truncate flex items-center justify-between cursor-pointer"
              >
                <span>{isAr ? 'رفع ملف استجابة JSON...' : 'Upload JSON response file...'}</span>
                <span>📁</span>
              </button>
            </div>

            {/* Pasted text area */}
            <textarea
              rows={4}
              value={jsonInput}
              onChange={(e) => onJsonInputChange(e.target.value)}
              placeholder={isAr ? 'الصق استجابة JSON هنا...' : 'Paste response JSON here...'}
              className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-400 dark:focus:border-slate-700 font-mono text-[11px] leading-relaxed resize-y"
            />

            {/* Validation messages */}
            {errorMessage && (
              <div className="bg-red-50 text-red-750 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900/50 p-3 rounded-xl text-xs font-semibold">
                {errorMessage}
              </div>
            )}

            {validationErrors.length > 0 && (
              <div className="bg-red-50 text-red-750 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900/50 p-4 rounded-xl text-xs flex flex-col gap-2 font-mono">
                <span className="font-bold">{isAr ? 'أخطاء التحقق:' : 'Validation Errors:'}</span>
                <ul className="list-disc list-inside space-y-1">
                  {validationErrors.map((err, i) => (
                    <li key={i}>{err}</li>
                  ))}
                </ul>
              </div>
            )}

            {validationWarnings.length > 0 && (
              <div className="bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50 p-4 rounded-xl text-xs flex flex-col gap-2 font-mono">
                <span className="font-bold">{isAr ? 'تحذيرات:' : 'Warnings:'}</span>
                <ul className="list-disc list-inside space-y-1">
                  {validationWarnings.map((wrn, i) => (
                    <li key={i}>{wrn}</li>
                  ))}
                </ul>
              </div>
            )}

            {validationSuccessMessage && (
              <div className="bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border border-emerald-250 dark:border-emerald-900 p-3 rounded-xl text-xs font-semibold font-mono">
                {validationSuccessMessage}
              </div>
            )}

            {/* Trigger Validation & Import */}
            <button
              type="button"
              onClick={() => onValidateJson()}
              disabled={isValidating || !jsonInput.trim()}
              className="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 disabled:bg-emerald-700/20 disabled:text-emerald-500/50 disabled:cursor-not-allowed text-white font-semibold py-2.5 px-4 rounded-xl text-xs transition-none flex items-center justify-center gap-2 cursor-pointer"
            >
              {isValidating ? (isAr ? 'جاري التحقق...' : 'Validating...') : (isAr ? 'التحقق والاستيراد' : 'Validate & Import')}
            </button>
          </div>
        </div>
      )}

      {/* Segmentation Preview & Approval Section */}
      {isSegmentation && segmentsPreview && segmentsPreview.length > 0 && (
        <div className="border-t border-slate-100 dark:border-slate-800/60 pt-4 flex flex-col gap-4">
          <div className="flex items-center justify-between">
            <h4 className="text-xs font-semibold text-slate-700 dark:text-slate-350">
              {isAr ? 'معاينة المقاطع البصرية' : 'Segments Preview'}
            </h4>
            {isRepaired && (
              <span className="bg-amber-100 dark:bg-amber-950/40 text-amber-800 dark:text-amber-400 px-2.5 py-0.5 rounded text-[10px] font-semibold border border-amber-200 dark:border-amber-900/60">
                {isAr ? 'مصلح تلقائيًا' : 'Auto-Repaired'}
              </span>
            )}
          </div>

          <div className="max-h-[300px] overflow-y-auto border border-slate-100 dark:border-slate-800/60 rounded-xl">
            <table className="w-full text-left border-collapse text-xs font-sans">
              <thead>
                <tr className="bg-slate-50 dark:bg-slate-950 border-b border-slate-100 dark:border-slate-850/60 text-slate-500 dark:text-slate-400 font-semibold">
                  <th className="p-2 w-12 text-center">#</th>
                  <th className="p-2 text-right">النص العربي</th>
                  <th className="p-2">Translation</th>
                  <th className="p-2">Tafsir</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-50 dark:divide-slate-850/30 text-slate-750 dark:text-slate-300">
                {segmentsPreview.map((seg) => (
                  <tr key={seg.order} className="hover:bg-slate-50/50 dark:hover:bg-slate-900/50">
                    <td className="p-2 text-center font-mono text-[10px] text-slate-400">{seg.order}</td>
                    <td className="p-2 text-right font-semibold font-sans text-slate-900 dark:text-slate-100 text-sm" dir="rtl">{seg.arabic}</td>
                    <td className="p-2 leading-relaxed text-[11px]">{seg.translation}</td>
                    <td className="p-2 leading-relaxed text-[10px] text-slate-400">{seg.tafsir}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Generator metadata & Approval */}
          <div className="flex flex-col gap-4 border-t border-slate-100 dark:border-slate-850/40 pt-4">
            <div className="grid grid-cols-3 gap-3">
              <div className="flex flex-col gap-1">
                <label className="text-[10px] font-semibold text-slate-500 dark:text-slate-400">{isAr ? 'نوع المولد' : 'Generator Type'}</label>
                <select
                  value={generatorType}
                  onChange={(e) => onGeneratorTypeChange?.(e.target.value)}
                  className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-2 py-1.5 rounded-lg text-xs"
                >
                  <option value="gemini">Gemini</option>
                  <option value="openai">OpenAI</option>
                  <option value="anthropic">Anthropic</option>
                  <option value="deepseek">DeepSeek</option>
                  <option value="manual">Manual</option>
                </select>
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-[10px] font-semibold text-slate-500 dark:text-slate-400">{isAr ? 'الموديل' : 'Generator Model'}</label>
                <input
                  type="text"
                  value={generatorModel}
                  onChange={(e) => onGeneratorModelChange?.(e.target.value)}
                  placeholder="e.g. gemini-2.5-pro"
                  className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-2 py-1.5 rounded-lg text-xs"
                />
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-[10px] font-semibold text-slate-500 dark:text-slate-400">{isAr ? 'الزمن المستغرق (ملي ثانية)' : 'Latency (ms)'}</label>
                <input
                  type="number"
                  value={latencyMs}
                  onChange={(e) => onLatencyMsChange?.(e.target.value)}
                  placeholder="e.g. 1500"
                  className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-2 py-1.5 rounded-lg text-xs"
                />
              </div>
            </div>

            <button
              type="button"
              onClick={onApproveJson}
              disabled={saving}
              className="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 px-4 rounded-xl text-xs cursor-pointer"
            >
              {saving ? (isAr ? 'جاري الحفظ...' : 'Saving...') : (isAr ? 'تأكيد وحفظ المقاطع البصرية' : 'Approve & Save Segments')}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
