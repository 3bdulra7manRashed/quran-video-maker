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
      <div className="bg-surface-container rounded-xl border border-surface-variant/40 p-5 flex flex-col gap-4 shadow-sm font-sans">
        <div className="flex items-center justify-between border-b border-surface-variant/30 pb-3">
          <div className="flex items-center gap-2">
            <span className="p-1 rounded bg-primary/10 text-primary text-base font-bold">{icon}</span>
            <div>
              <h3 className="text-xs font-bold text-on-surface flex items-center gap-1.5">
                <span>{title}</span>
              </h3>
              <p className="text-[10px] text-on-surface-variant">
                {description}
              </p>
            </div>
          </div>
          <span className="px-2 py-0.5 rounded text-[10px] font-mono bg-primary/15 text-primary border border-primary/30 font-semibold">
            {isAr ? 'مزامنة صوتية' : 'Audio Sync'}
          </span>
        </div>

        {approvedSegments && approvedSegments.length > 0 ? (
          <div className="flex flex-col gap-3">
            <button
              type="button"
              onClick={() => setIsRecording(true)}
              className="w-full py-2.5 px-4 rounded-xl text-xs font-bold bg-primary text-on-primary hover:bg-tertiary shadow-[0_0_12px_rgba(242,202,80,0.25)] flex items-center justify-center gap-2 cursor-pointer transition-all"
            >
              <span className="material-symbols-outlined text-[18px]">mic</span>
              <span>{isAr ? 'فتح مسجل التوقيت التفاعلي (Live Timing Recorder)' : 'Open Segment Timing Recorder'}</span>
            </button>

            {validationSuccessMessage && (
              <div className="bg-secondary/10 text-secondary border border-secondary/30 p-3 rounded-xl text-xs font-semibold font-mono flex items-center gap-1.5">
                <span className="material-symbols-outlined text-[16px]">check_circle</span>
                <span>{validationSuccessMessage}</span>
              </div>
            )}
            {errorMessage && (
              <div className="bg-red-500/10 text-red-400 border border-red-500/30 p-3 rounded-xl text-xs font-semibold flex items-center gap-1.5">
                <span className="material-symbols-outlined text-[16px]">error</span>
                <span>{errorMessage}</span>
              </div>
            )}

            {jsonInput && (
              <div className="flex flex-col gap-2 pt-2 border-t border-surface-variant/20">
                <button
                  type="button"
                  onClick={() => onValidateJson?.(jsonInput)}
                  disabled={isValidating}
                  className="w-full bg-secondary text-on-secondary hover:bg-secondary/90 disabled:opacity-50 font-bold py-2.5 px-4 rounded-xl text-xs flex items-center justify-center gap-2 cursor-pointer transition shadow-[0_0_12px_rgba(69,223,164,0.25)]"
                >
                  {isValidating ? (
                    <>
                      <div className="w-3.5 h-3.5 border-2 border-on-secondary border-t-transparent rounded-full animate-spin"></div>
                      <span>{isAr ? 'جاري الحفظ والتثبيت...' : 'Saving & Persisting...'}</span>
                    </>
                  ) : (
                    <>
                      <span className="material-symbols-outlined text-[16px]">save</span>
                      <span>{isAr ? 'حفظ وتثبيت التوقيت في قاعدة البيانات' : 'Save & Persist to Database'}</span>
                    </>
                  )}
                </button>

                <details className="mt-1">
                  <summary className="text-[11px] text-on-surface-variant cursor-pointer select-none hover:text-on-surface flex items-center gap-1">
                    <span className="material-symbols-outlined text-[14px]">code</span>
                    <span>{isAr ? 'عرض وتعديل صياغة JSON للتوقيت' : 'View & Edit Timing JSON'}</span>
                  </summary>
                  <div className="mt-2 flex flex-col gap-2">
                    <textarea
                      rows={6}
                      value={jsonInput}
                      onChange={(e) => onJsonInputChange?.(e.target.value)}
                      className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface p-3 rounded-xl font-mono text-xs focus:outline-none focus:border-primary"
                    />
                    <button
                      type="button"
                      onClick={() => onValidateJson?.(jsonInput)}
                      disabled={isValidating}
                      className="self-end bg-surface-container-high hover:bg-surface-bright text-on-surface text-xs font-bold py-1.5 px-3 rounded-lg border border-surface-variant/40 cursor-pointer"
                    >
                      {isAr ? 'حفظ واستيراد' : 'Save & Import'}
                    </button>
                  </div>
                </details>
              </div>
            )}
          </div>
        ) : (
          <div className="p-3 rounded-xl bg-primary/10 border border-primary/30 text-xs flex flex-col gap-1 text-primary">
            <span className="font-bold flex items-center gap-1">
              <span className="material-symbols-outlined text-[16px]">info</span>
              <span>{isAr ? 'تنبيه:' : 'Notice:'}</span>
            </span>
            <span>
              {isAr
                ? 'لم يتم العثور على مقاطع معتمدة بعد. يرجى توليد وتأكيد المقاطع البصرية أولاً في الخطوة السابقة.'
                : 'No approved segments found. Please generate and approve segments first in the previous step.'}
            </span>
          </div>
        )}
      </div>
    );
  }

  return (
    <div className="bg-surface-container rounded-xl border border-surface-variant/40 p-5 flex flex-col gap-4 shadow-sm font-sans">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-surface-variant/30 pb-3">
        <div className="flex items-center gap-2">
          <span className="p-1 rounded bg-primary/10 text-primary text-base font-bold">{icon}</span>
          <div>
            <h3 className="text-xs font-bold text-on-surface flex items-center gap-1.5">
              <span>{title}</span>
            </h3>
            <p className="text-[10px] text-on-surface-variant">
              {description}
            </p>
          </div>
        </div>
        <span className="px-2 py-0.5 rounded text-[10px] font-mono bg-secondary/15 text-secondary border border-secondary/30 font-semibold">
          AI
        </span>
      </div>

      {/* Inputs Section */}
      {(requiresMarkers || requiresMushafLineInfo) && (
        <div className="flex flex-col gap-3 border-t border-surface-variant/20 pt-3">
          {requiresMushafLineInfo && (
            <div className="grid grid-cols-2 gap-3">
              <div className="flex flex-col gap-1">
                <label className="text-[11px] font-semibold text-on-surface-variant">
                  {isAr ? 'صفحة البدء *' : 'Start Page *'}
                </label>
                <input
                  type="number"
                  min={1}
                  value={startPage}
                  onChange={(e) => onStartPageChange?.(e.target.value)}
                  placeholder="e.g. 578"
                  className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface px-3 py-1.5 rounded-lg focus:outline-none focus:border-primary font-mono text-xs"
                  required
                />
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-[11px] font-semibold text-on-surface-variant">
                  {isAr ? 'سطر البدء *' : 'Start Line *'}
                </label>
                <input
                  type="number"
                  min={1}
                  max={15}
                  value={startLine}
                  onChange={(e) => onStartLineChange?.(e.target.value)}
                  placeholder="e.g. 12"
                  className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface px-3 py-1.5 rounded-lg focus:outline-none focus:border-primary font-mono text-xs"
                  required
                />
              </div>
            </div>
          )}

          {requiresMarkers && (
            <div className="flex flex-col gap-1">
              <label className="text-[11px] font-semibold text-on-surface-variant">
                {isAr ? 'علامات أدوبي أودیشن *' : 'Adobe Audition Markers *'}
              </label>
              <textarea
                rows={3}
                value={markers}
                onChange={(e) => onMarkersChange?.(e.target.value)}
                placeholder={"0:00.000\n0:12.034\n0:27.177"}
                className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface px-3 py-2 rounded-lg focus:outline-none focus:border-primary font-mono text-xs leading-relaxed"
                required
              />
              <span className="text-[10px] text-on-surface-variant/70">
                {isAr ? 'الصق علامات Adobe Audition تمامًا كما تم تصديرها من البرنامج.' : 'Paste Adobe Audition markers exactly as exported.'}
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
          className="w-full py-2 px-3 rounded-lg bg-primary text-on-primary font-bold text-xs flex items-center justify-center gap-2 shadow-[0_0_12px_rgba(242,202,80,0.25)] hover:bg-tertiary transition-all cursor-pointer disabled:opacity-40"
        >
          {generatingPrompt ? (
            <>
              <div className="w-3.5 h-3.5 border-2 border-on-primary border-t-transparent rounded-full animate-spin"></div>
              <span>{isAr ? 'جاري توليد الموجه...' : 'Generating Prompt...'}</span>
            </>
          ) : (
            <>
              <span className="material-symbols-outlined text-[16px]">smart_toy</span>
              <span>{isAr ? 'توليد موجه الذكاء الاصطناعي (Generate AI Prompt)' : 'Generate AI Prompt'}</span>
            </>
          )}
        </button>
      </div>

      {/* Prompt Block */}
      {generatedPrompt && (
        <div className="bg-surface-container-lowest rounded-xl p-3 border border-surface-variant/40 flex flex-col gap-2">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-medium text-on-surface-variant flex items-center gap-1">
              <span className="material-symbols-outlined text-[14px] text-primary">terminal</span>
              <span>{isAr ? 'الموجه المولد (System Prompt)' : 'Generated Prompt'}</span>
            </span>
            <button
              type="button"
              onClick={onCopyPrompt}
              className="flex items-center gap-1 text-[11px] text-primary hover:text-tertiary font-semibold px-2 py-0.5 rounded bg-primary/10 hover:bg-primary/20 transition-colors cursor-pointer"
            >
              <span className="material-symbols-outlined text-[14px]">
                {isCopied ? 'check' : 'content_copy'}
              </span>
              <span>{isCopied ? (isAr ? 'تم النسخ ✓' : 'Copied ✓') : (isAr ? 'نسخ الموجه' : 'Copy')}</span>
            </button>
          </div>
          <div className="bg-black/60 rounded-lg p-2.5 font-mono text-[11px] text-emerald-400/90 leading-relaxed max-h-36 overflow-y-auto border border-surface-variant/20" dir="ltr">
            <pre className="whitespace-pre-wrap font-mono">{generatedPrompt}</pre>
          </div>
        </div>
      )}

      {/* JSON Payload Input Block */}
      {generatedPrompt && (
        <div className="border-t border-surface-variant/20 pt-3 flex flex-col gap-3">
          <div className="flex items-center justify-between text-[11px]">
            <span className="font-semibold text-on-surface flex items-center gap-1">
              <span className="material-symbols-outlined text-[14px] text-secondary">data_object</span>
              <span>{isAr ? 'استجابة الذكاء الاصطناعي (JSON Result)' : 'AI Response JSON'}</span>
            </span>
            <span className="text-[10px] text-on-surface-variant">
              {isAr ? 'الصق الكود أو ارفع الملف' : 'Paste or upload JSON file'}
            </span>
          </div>

          <div className="flex flex-col gap-2.5">
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
                className="w-full border border-dashed border-surface-variant/60 rounded-lg p-2 bg-surface-container-lowest flex items-center justify-center gap-2 text-xs text-on-surface-variant hover:border-primary cursor-pointer transition-colors"
              >
                <span className="material-symbols-outlined text-primary text-[18px]">folder_open</span>
                <span>{isAr ? 'رفع ملف استجابة JSON...' : 'Upload JSON file...'}</span>
              </button>
            </div>

            {/* Pasted text area */}
            <textarea
              rows={4}
              value={jsonInput}
              onChange={(e) => onJsonInputChange(e.target.value)}
              placeholder={isAr ? 'الصق استجابة JSON هنا...' : 'Paste response JSON here...'}
              className="w-full bg-surface-container-lowest border border-surface-variant/40 rounded-lg p-2 text-xs text-on-surface font-mono placeholder:text-on-surface-variant/40 focus:border-primary focus:ring-0 resize-y"
              dir="ltr"
            />

            {/* Validation messages */}
            {errorMessage && (
              <div className="bg-red-500/10 text-red-400 border border-red-500/30 p-2.5 rounded-lg text-xs font-semibold flex items-center gap-1.5">
                <span className="material-symbols-outlined text-[14px]">error</span>
                <span>{errorMessage}</span>
              </div>
            )}

            {validationErrors.length > 0 && (
              <div className="bg-red-500/10 text-red-400 border border-red-500/30 p-3 rounded-lg text-xs flex flex-col gap-1 font-mono">
                <span className="font-bold">{isAr ? 'أخطاء التحقق:' : 'Validation Errors:'}</span>
                <ul className="list-disc list-inside space-y-0.5">
                  {validationErrors.map((err, i) => (
                    <li key={i}>{err}</li>
                  ))}
                </ul>
              </div>
            )}

            {validationWarnings.length > 0 && (
              <div className="bg-amber-500/10 text-amber-400 border border-amber-500/30 p-3 rounded-lg text-xs flex flex-col gap-1 font-mono">
                <span className="font-bold">{isAr ? 'تحذيرات:' : 'Warnings:'}</span>
                <ul className="list-disc list-inside space-y-0.5">
                  {validationWarnings.map((wrn, i) => (
                    <li key={i}>{wrn}</li>
                  ))}
                </ul>
              </div>
            )}

            {validationSuccessMessage && (
              <div className="bg-secondary/10 text-secondary border border-secondary/30 p-2.5 rounded-lg text-xs font-semibold font-mono flex items-center gap-1.5">
                <span className="material-symbols-outlined text-[14px]">check_circle</span>
                <span>{validationSuccessMessage}</span>
              </div>
            )}

            {/* Trigger Validation & Import */}
            <button
              type="button"
              onClick={() => onValidateJson()}
              disabled={isValidating || !jsonInput.trim()}
              className="py-2 px-3 rounded-lg bg-secondary/15 hover:bg-secondary/25 text-secondary border border-secondary/40 font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer disabled:opacity-40"
            >
              {isValidating ? (
                <>
                  <div className="w-3.5 h-3.5 border-2 border-secondary border-t-transparent rounded-full animate-spin"></div>
                  <span>{isAr ? 'جاري التحقق...' : 'Validating...'}</span>
                </>
              ) : (
                <>
                  <span className="material-symbols-outlined text-[16px]">check</span>
                  <span>{isAr ? 'التحقق واستيراد المقاطع (Validate & Import)' : 'Validate & Import'}</span>
                </>
              )}
            </button>
          </div>
        </div>
      )}

      {/* Segmentation Preview & Approval Section */}
      {isSegmentation && segmentsPreview && segmentsPreview.length > 0 && (
        <div className="border-t border-surface-variant/20 pt-3 flex flex-col gap-3">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-bold text-on-surface flex items-center gap-1">
              <span className="material-symbols-outlined text-[14px] text-primary">view_list</span>
              <span>{isAr ? 'معاينة المقاطع البصرية المعتمدة' : 'Approved Segments Preview'}</span>
            </span>
            {isRepaired && (
              <span className="bg-amber-500/15 text-amber-400 px-2 py-0.5 rounded text-[10px] font-semibold border border-amber-500/30">
                {isAr ? 'مصلح تلقائيًا ✓' : 'Auto-Repaired ✓'}
              </span>
            )}
          </div>

          <div className="max-h-[280px] overflow-y-auto border border-surface-variant/30 rounded-xl bg-surface-container-lowest">
            <table className="w-full text-start border-collapse text-xs font-sans">
              <thead>
                <tr className="bg-surface-container-high/60 border-b border-surface-variant/30 text-on-surface-variant font-semibold text-[10px] uppercase">
                  <th className="p-2 w-10 text-center">#</th>
                  <th className="p-2 text-start">{isAr ? 'النص القرآني' : 'Arabic Text'}</th>
                  <th className="p-2 text-start">{isAr ? 'الترجمة' : 'Translation'}</th>
                  <th className="p-2 text-start">{isAr ? 'التفسير' : 'Tafsir'}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-surface-variant/15 text-on-surface">
                {segmentsPreview.map((seg) => (
                  <tr key={seg.order} className="hover:bg-surface-container-high/40 transition-colors">
                    <td className="p-2 text-center font-mono text-[10px] text-primary font-bold">{seg.order}</td>
                    <td className="p-2 font-quran text-sm font-semibold text-primary leading-relaxed" dir="rtl">{seg.arabic}</td>
                    <td className="p-2 text-[11px] text-on-surface-variant leading-relaxed">{seg.translation}</td>
                    <td className="p-2 text-[10px] text-on-surface-variant/70 leading-relaxed">{seg.tafsir}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Generator metadata & Approval */}
          <div className="flex flex-col gap-3 border-t border-surface-variant/20 pt-3">
            <div className="grid grid-cols-3 gap-2">
              <div className="flex flex-col gap-1">
                <label className="text-[10px] font-semibold text-on-surface-variant">{isAr ? 'نوع المولد' : 'Generator'}</label>
                <select
                  value={generatorType}
                  onChange={(e) => onGeneratorTypeChange?.(e.target.value)}
                  className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface px-2 py-1.5 rounded-lg text-xs focus:border-primary"
                >
                  <option value="gemini" className="bg-zinc-900 text-white">Gemini</option>
                  <option value="openai" className="bg-zinc-900 text-white">OpenAI</option>
                  <option value="anthropic" className="bg-zinc-900 text-white">Anthropic</option>
                  <option value="deepseek" className="bg-zinc-900 text-white">DeepSeek</option>
                  <option value="manual" className="bg-zinc-900 text-white">Manual</option>
                </select>
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-[10px] font-semibold text-on-surface-variant">{isAr ? 'الموديل' : 'Model'}</label>
                <input
                  type="text"
                  value={generatorModel}
                  onChange={(e) => onGeneratorModelChange?.(e.target.value)}
                  placeholder="gemini-2.5-pro"
                  className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface px-2 py-1.5 rounded-lg text-xs focus:border-primary font-mono"
                />
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-[10px] font-semibold text-on-surface-variant">{isAr ? 'الزمن (ملي ثانية)' : 'Latency (ms)'}</label>
                <input
                  type="number"
                  value={latencyMs}
                  onChange={(e) => onLatencyMsChange?.(e.target.value)}
                  placeholder="1500"
                  className="w-full bg-surface-container-lowest border border-surface-variant/40 text-on-surface px-2 py-1.5 rounded-lg text-xs focus:border-primary font-mono"
                />
              </div>
            </div>

            <button
              type="button"
              onClick={onApproveJson}
              disabled={saving}
              className="w-full py-2.5 px-4 rounded-xl bg-secondary text-on-secondary hover:bg-secondary/90 font-bold text-xs shadow-[0_0_12px_rgba(69,223,164,0.25)] flex items-center justify-center gap-2 cursor-pointer transition-all disabled:opacity-50"
            >
              {saving ? (
                <>
                  <div className="w-3.5 h-3.5 border-2 border-on-secondary border-t-transparent rounded-full animate-spin"></div>
                  <span>{isAr ? 'جاري الحفظ...' : 'Saving...'}</span>
                </>
              ) : (
                <>
                  <span className="material-symbols-outlined text-[16px]">verified</span>
                  <span>{isAr ? 'تأكيد وحفظ المقاطع البصرية في قاعدة البيانات' : 'Approve & Save Segments'}</span>
                </>
              )}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
