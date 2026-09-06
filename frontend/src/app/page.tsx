'use client';

import React, { useEffect, useState, useRef } from 'react';
import { useApi } from '@/context/ApiContext';
import { Reciter, Surah, DatasetStatus } from '@/services/api';
import ReciterSelect from '@/components/ReciterSelect';
import SurahSelect from '@/components/SurahSelect';
import DatasetStatusCard from '@/components/DatasetStatusCard';
import DatasetActions from '@/components/DatasetActions';
import RenderOptions from '@/components/RenderOptions';
import GenerateVideoCard from '@/components/GenerateVideoCard';
import AITaskCard from '@/components/AITaskCard';
import { LAYOUTS } from '@/config/layouts';
import { AI_TASKS } from '@/config/aiTasks';
import {
  generateSegmentationPrompt,
  verifySegmentationJson,
  approveSegmentationJson,
  generateSegmentTimingPrompt,
  importSegmentTimingJson,
  generateLineTimingPrompt,
  importLineTimingJson,
} from '@/services/ai';
import { useT } from '@/hooks/useT';
import { useLanguage } from '@/hooks/useLanguage';

export type RenderScope = 'full' | 'single' | 'range';

export default function ConsolePage() {
  const { api, apiUrl } = useApi();
  const t = useT();
  const { language } = useLanguage();
  const isAr = language === 'ar';

  const [reciters, setReciters] = useState<Reciter[]>([]);
  const [surahs, setSurahs] = useState<Surah[]>([]);
  
  const [selectedReciter, setSelectedReciter] = useState<string>('');
  const [selectedSurah, setSelectedSurah] = useState<number | ''>('');

  const [status, setStatus] = useState<DatasetStatus | null>(null);
  const [loadingStatus, setLoadingStatus] = useState(false);
  const [loadingPrereqs, setLoadingPrereqs] = useState(true);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Polling ref to clear interval safely on selection change or unmount
  const pollingIntervalRef = useRef<NodeJS.Timeout | null>(null);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (pollingIntervalRef.current) {
        clearInterval(pollingIntervalRef.current);
      }
    };
  }, []);

  // Render scope states
  const [scope, setScope] = useState<RenderScope>('full');
  const [ayahNumber, setAyahNumber] = useState<number>(1);
  const [fromAyah, setFromAyah] = useState<number>(1);
  const [toAyah, setToAyah] = useState<number>(1);

  // Layout states
  const [layout, setLayout] = useState<string>('reels');
  const [maxLines, setMaxLines] = useState<number>(1);

  // Translation & Tafsir states
  const [withTranslation, setWithTranslation] = useState<boolean>(false);
  const [translationSource, setTranslationSource] = useState<string>('sahih_international');
  const [withTafsir, setWithTafsir] = useState<boolean>(false);

  // Theme state
  const [useQuranMeTheme, setUseQuranMeTheme] = useState<boolean>(false);

  // Guide accordion state
  const [guideOpen, setGuideOpen] = useState<boolean>(false);

  // AI content states
  const [useGeneratedContent, setUseGeneratedContent] = useState<boolean>(false);
  const [persistToDatabase, setPersistToDatabase] = useState<boolean>(true);

  // ===================================================
  // AI TASK 1: SEGMENTATION STATES
  // ===================================================
  const [segPrompt, setSegPrompt] = useState<string>('');
  const [segJsonInput, setSegJsonInput] = useState<string>('');
  const [segCopied, setSegCopied] = useState<boolean>(false);
  const [segGenerating, setSegGenerating] = useState<boolean>(false);
  const [segValidating, setSegValidating] = useState<boolean>(false);
  const [segSuccessMsg, setSegSuccessMsg] = useState<string | null>(null);
  const [segErrors, setSegErrors] = useState<string[]>([]);
  const [segWarnings, setSegWarnings] = useState<string[]>([]);
  const [segErrorMsg, setSegErrorMsg] = useState<string | null>(null);
  const [segPreview, setSegPreview] = useState<any[] | undefined>(undefined);
  const [segRepaired, setSegRepaired] = useState<boolean>(false);
  const [segSaving, setSegSaving] = useState<boolean>(false);
  const [segGenType, setSegGenType] = useState<string>('gemini');
  const [segGenModel, setSegGenModel] = useState<string>('gemini-2.5-pro');
  const [segLatencyMs, setSegLatencyMs] = useState<string>('');

  // ===================================================
  // AI TASK 2: SEGMENT TIMINGS STATES
  // ===================================================
  const [segTimMarkers, setSegTimMarkers] = useState<string>('');
  const [segTimPrompt, setSegTimPrompt] = useState<string>('');
  const [segTimJsonInput, setSegTimJsonInput] = useState<string>('');
  const [lastSegTimJson, setLastSegTimJson] = useState<string | undefined>();
  const [segTimCopied, setSegTimCopied] = useState<boolean>(false);
  const [segTimGenerating, setSegTimGenerating] = useState<boolean>(false);
  const [segTimValidating, setSegTimValidating] = useState<boolean>(false);
  const [segTimSuccessMsg, setSegTimSuccessMsg] = useState<string | null>(null);
  const [segTimErrors, setSegTimErrors] = useState<string[]>([]);
  const [segTimWarnings, setSegTimWarnings] = useState<string[]>([]);
  const [segTimErrorMsg, setSegTimErrorMsg] = useState<string | null>(null);

  // ===================================================
  // AI TASK 3: LINE TIMINGS STATES
  // ===================================================
  const [lineTimStartPage, setLineTimStartPage] = useState<string>('');
  const [lineTimStartLine, setLineTimStartLine] = useState<string>('');
  const [lineTimMarkers, setLineTimMarkers] = useState<string>('');
  const [lineTimPrompt, setLineTimPrompt] = useState<string>('');
  const [lineTimJsonInput, setLineTimJsonInput] = useState<string>('');
  const [lineTimCopied, setLineTimCopied] = useState<boolean>(false);
  const [lineTimGenerating, setLineTimGenerating] = useState<boolean>(false);
  const [lineTimValidating, setLineTimValidating] = useState<boolean>(false);
  const [lineTimSuccessMsg, setLineTimSuccessMsg] = useState<string | null>(null);
  const [lineTimErrors, setLineTimErrors] = useState<string[]>([]);
  const [lineTimWarnings, setLineTimWarnings] = useState<string[]>([]);
  const [lineTimErrorMsg, setLineTimErrorMsg] = useState<string | null>(null);

  // Get max ayahs for current selection
  const maxAyahs = selectedSurah !== '' 
    ? surahs.find(s => s.number === selectedSurah)?.verses_count || 114
    : 114;

  // Load Reciters & Surahs
  useEffect(() => {
    async function loadData() {
      setLoadingPrereqs(true);
      setErrorMsg(null);
      try {
        const [reciterList, surahList] = await Promise.all([
          api.getReciters(),
          api.getSurahs()
        ]);
        setReciters(reciterList);
        setSurahs(surahList);
      } catch (err: any) {
        setErrorMsg(t('common.errorConnectBackend'));
        console.error(err);
      } finally {
        setLoadingPrereqs(false);
      }
    }
    loadData();
  }, [apiUrl]); // Reload when API URL changes

  // Fetch status whenever selections change
  useEffect(() => {
    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }

    if (selectedReciter && selectedSurah !== '') {
      loadDatasetStatus();
    } else {
      setStatus(null);
    }

    // Reset task states on selection change
    setSegPrompt('');
    setSegJsonInput('');
    setSegPreview(undefined);
    setSegErrors([]);
    setSegWarnings([]);
    setSegSuccessMsg(null);
    setSegErrorMsg(null);

    setSegTimPrompt('');
    setSegTimJsonInput('');
    setSegTimErrors([]);
    setSegTimWarnings([]);
    setSegTimSuccessMsg(null);
    setSegTimErrorMsg(null);

    setLineTimPrompt('');
    setLineTimJsonInput('');
    setLineTimErrors([]);
    setLineTimWarnings([]);
    setLineTimSuccessMsg(null);
    setLineTimErrorMsg(null);
  }, [selectedReciter, selectedSurah]);

  // Sync range limits when Surah changes
  useEffect(() => {
    if (selectedSurah !== '') {
      setAyahNumber(1);
      setFromAyah(1);
      setToAyah(maxAyahs);
    }
  }, [selectedSurah, maxAyahs]);

  async function loadDatasetStatus() {
    if (!selectedReciter || selectedSurah === '') return;
    setLoadingStatus(true);
    try {
      const stats = await api.getDatasetStatus(selectedReciter, selectedSurah);
      setStatus(stats);
    } catch (err) {
      console.error('Failed to load status:', err);
    } finally {
      setLoadingStatus(false);
    }
  }

  const handlePrepare = async () => {
    if (!selectedReciter || selectedSurah === '') return;

    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }

    // Trigger backend dataset preparation job
    await api.prepareDataset(selectedReciter, selectedSurah);

    setLoadingStatus(true);

    return new Promise<void>((resolve, reject) => {
      let attempts = 0;
      const maxAttempts = 80; // 120s total (80 * 1500ms)
      const targetReciter = selectedReciter;
      const targetSurah = selectedSurah;

      pollingIntervalRef.current = setInterval(async () => {
        attempts++;
        try {
          const stats = await api.getDatasetStatus(targetReciter, targetSurah);
          setStatus(stats);

          const isReady = stats.renderable || (stats.glyphs && stats.audio);

          if (isReady || attempts >= maxAttempts) {
            if (pollingIntervalRef.current) {
              clearInterval(pollingIntervalRef.current);
              pollingIntervalRef.current = null;
            }
            setLoadingStatus(false);

            if (isReady) {
              resolve();
            } else {
              reject(new Error('Dataset preparation timed out after 120 seconds.'));
            }
          }
        } catch (err) {
          console.error('Polling dataset status failed:', err);
          if (attempts >= maxAttempts) {
            if (pollingIntervalRef.current) {
              clearInterval(pollingIntervalRef.current);
              pollingIntervalRef.current = null;
            }
            setLoadingStatus(false);
            reject(new Error('Dataset status polling encountered an error.'));
          }
        }
      }, 1500);
    });
  };

  const handleUploadAudio = async (file: File) => {
    if (!selectedReciter || selectedSurah === '') return;
    await api.uploadAudio(selectedReciter, selectedSurah, file);
    await loadDatasetStatus();
  };

  const handleUploadTimings = async (file: File) => {
    if (!selectedReciter) return;
    await api.uploadTimings(selectedReciter, file);
    await loadDatasetStatus();
  };

  // ===================================================
  // TASK CALLBAK OPERATORS (SEGMENTATION)
  // ===================================================
  const runGenerateSegPrompt = async () => {
    if (selectedSurah === '') return;
    setSegGenerating(true);
    setSegErrorMsg(null);
    try {
      const prompt = await generateSegmentationPrompt(api, {
        surahNumber: Number(selectedSurah),
        reciterSlug: selectedReciter,
        scope,
        ayahNumber,
        fromAyah,
        toAyah,
      });
      setSegPrompt(prompt);
    } catch (err: any) {
      setSegErrorMsg(err.message || 'Failed to generate prompt');
    } finally {
      setSegGenerating(false);
    }
  };

  const runValidateSegJson = async () => {
    if (selectedSurah === '') return;
    setSegValidating(true);
    setSegErrorMsg(null);
    setSegSuccessMsg(null);
    setSegErrors([]);
    setSegWarnings([]);
    setSegPreview(undefined);
    setSegRepaired(false);

    try {
      JSON.parse(segJsonInput);
    } catch (e: any) {
      setSegErrorMsg(isAr ? `خطأ في صياغة JSON: ${e.message}` : `JSON syntax error: ${e.message}`);
      setSegValidating(false);
      return;
    }

    try {
      const res = await verifySegmentationJson(
        api,
        {
          surahNumber: Number(selectedSurah),
          reciterSlug: selectedReciter,
          scope,
          ayahNumber,
          fromAyah,
          toAyah,
        },
        segJsonInput
      );
      setSegErrors(res.errors);
      setSegWarnings(res.warnings);
      if (res.repairedJson) {
        setSegRepaired(true);
        setSegJsonInput(res.repairedJson);
      }
      if (res.isValid) {
        setSegSuccessMsg(isAr ? 'كود JSON صالح ومطابق للمواصفات!' : 'JSON is valid!');
        setSegPreview(res.segments);
      } else {
        setSegErrorMsg(t('common.contentPipeline.invalidJson'));
      }
    } catch (err: any) {
      setSegErrorMsg(err.message || 'Validation failed');
    } finally {
      setSegValidating(false);
    }
  };

  const runApproveSegJson = async () => {
    if (selectedSurah === '') return;
    setSegSaving(true);
    setSegErrorMsg(null);
    try {
      await approveSegmentationJson(
        api,
        {
          surahNumber: Number(selectedSurah),
          reciterSlug: selectedReciter,
          scope,
          ayahNumber,
          fromAyah,
          toAyah,
        },
        segJsonInput,
        segGenType,
        segGenModel,
        segLatencyMs ? Number(segLatencyMs) : undefined
      );
      setSegSuccessMsg(isAr ? 'تم حفظ وتأكيد المقاطع البصرية بنجاح!' : 'Segments approved and saved successfully!');
      setSegPreview(undefined);
      await loadDatasetStatus();
    } catch (err: any) {
      setSegErrorMsg(err.message || 'Failed to save approved JSON');
    } finally {
      setSegSaving(false);
    }
  };

  const copySegPrompt = () => {
    navigator.clipboard.writeText(segPrompt);
    setSegCopied(true);
    setTimeout(() => setSegCopied(false), 2000);
  };

  // ===================================================
  // TASK CALLBAK OPERATORS (SEGMENT TIMINGS)
  // ===================================================
  const runGenerateSegTimPrompt = async () => {
    if (selectedSurah === '') return;
    setSegTimGenerating(true);
    setSegTimErrorMsg(null);
    try {
      const prompt = await generateSegmentTimingPrompt(api, {
        surahNumber: Number(selectedSurah),
        reciterSlug: selectedReciter,
        scope,
        ayahNumber,
        fromAyah,
        toAyah,
        markers: segTimMarkers,
      });
      setSegTimPrompt(prompt);
    } catch (err: any) {
      setSegTimErrorMsg(err.message || 'Failed to generate prompt');
    } finally {
      setSegTimGenerating(false);
    }
  };

  const runImportSegTimJson = async (directJson?: string) => {
    if (selectedSurah === '') return;
    setSegTimValidating(true);
    setSegTimErrorMsg(null);
    setSegTimSuccessMsg(null);
    setSegTimErrors([]);
    setSegTimWarnings([]);

    const jsonToParse = directJson !== undefined ? directJson : segTimJsonInput;

    try {
      JSON.parse(jsonToParse);
    } catch (e: any) {
      setSegTimErrorMsg(isAr ? `خطأ في صياغة JSON: ${e.message}` : `JSON syntax error: ${e.message}`);
      setSegTimValidating(false);
      return;
    }

    try {
      const res = await importSegmentTimingJson(
        api,
        {
          surahNumber: Number(selectedSurah),
          reciterSlug: selectedReciter,
          scope,
          ayahNumber,
          fromAyah,
          toAyah,
        },
        jsonToParse
      );
      if (res.success) {
        setSegTimSuccessMsg(isAr ? 'تم استيراد وتثبيت توقيت المقاطع في قاعدة البيانات بنجاح!' : 'Segment timings imported & saved permanently!');
        setLastSegTimJson(jsonToParse);
        setUseGeneratedContent(true);
        await loadDatasetStatus();
      }
    } catch (err: any) {
      setSegTimErrorMsg(err.message || 'Failed to import segment timings JSON');
    } finally {
      setSegTimValidating(false);
    }
  };

  const copySegTimPrompt = () => {
    navigator.clipboard.writeText(segTimPrompt);
    setSegTimCopied(true);
    setTimeout(() => setSegTimCopied(false), 2000);
  };

  // ===================================================
  // TASK CALLBAK OPERATORS (LINE TIMINGS)
  // ===================================================
  const runGenerateLineTimPrompt = async () => {
    if (selectedSurah === '') return;
    setLineTimGenerating(true);
    setLineTimErrorMsg(null);
    try {
      const prompt = await generateLineTimingPrompt(api, {
        surahNumber: Number(selectedSurah),
        reciterSlug: selectedReciter,
        scope,
        ayahNumber,
        fromAyah,
        toAyah,
        markers: lineTimMarkers,
        startPage: lineTimStartPage ? Number(lineTimStartPage) : undefined,
        startLine: lineTimStartLine ? Number(lineTimStartLine) : undefined,
      });
      setLineTimPrompt(prompt);
    } catch (err: any) {
      setLineTimErrorMsg(err.message || 'Failed to generate prompt');
    } finally {
      setLineTimGenerating(false);
    }
  };

  const runImportLineTimJson = async () => {
    if (selectedSurah === '') return;
    setLineTimValidating(true);
    setLineTimErrorMsg(null);
    setLineTimSuccessMsg(null);
    setLineTimErrors([]);
    setLineTimWarnings([]);

    try {
      JSON.parse(lineTimJsonInput);
    } catch (e: any) {
      setLineTimErrorMsg(isAr ? `خطأ في صياغة JSON: ${e.message}` : `JSON syntax error: ${e.message}`);
      setLineTimValidating(false);
      return;
    }

    try {
      const res = await importLineTimingJson(
        api,
        {
          surahNumber: Number(selectedSurah),
          reciterSlug: selectedReciter,
          scope,
          ayahNumber,
          fromAyah,
          toAyah,
        },
        lineTimJsonInput
      );
      if (res.success) {
        setLineTimSuccessMsg(isAr ? 'تم استيراد توقيت السطور بنجاح!' : 'Line timings imported successfully!');
        await loadDatasetStatus();
      }
    } catch (err: any) {
      setLineTimErrorMsg(err.message || 'Failed to import line timings JSON');
    } finally {
      setLineTimValidating(false);
    }
  };

  const copyLineTimPrompt = () => {
    navigator.clipboard.writeText(lineTimPrompt);
    setLineTimCopied(true);
    setTimeout(() => setLineTimCopied(false), 2000);
  };

  const currentLayoutConfig = LAYOUTS[layout];

  return (
    <div className="flex flex-col gap-6">
      {/* Title */}
      <div className="border-b border-slate-200 dark:border-slate-800 pb-4">
        <h1 className="text-2xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center justify-between">
          <span>{t('render.pageTitle')}</span>
        </h1>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
          {t('render.pageDescription')}
        </p>
      </div>

      {/* Collapsible Accordion Guide */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs flex flex-col gap-2 transition-all">
        <button
          onClick={() => setGuideOpen(!guideOpen)}
          suppressHydrationWarning
          className="flex items-center justify-between w-full font-semibold text-slate-900 dark:text-slate-100 focus:outline-none cursor-pointer"
        >
          <span>{isAr ? '📖 طريقة العمل' : '📖 How it works'}</span>
          <span className="text-slate-450 dark:text-slate-500">{guideOpen ? '▲' : '▼'}</span>
        </button>
        {guideOpen && (
          <ol className="list-decimal list-inside space-y-1 text-slate-500 dark:text-slate-400 mt-2 border-t border-slate-100 dark:border-slate-800/60 pt-2">
            <li>{isAr ? 'اختر القارئ' : 'Select a reciter'}</li>
            <li>{isAr ? 'اختر التخطيط' : 'Select a layout'}</li>
            <li>{isAr ? 'اختر السورة ومدى الآيات' : 'Choose Surah and Ayahs'}</li>
            <li>{isAr ? 'اختياري: ولد أو الصق ملف JSON' : 'Optionally generate or paste JSON'}</li>
            <li>{isAr ? 'اضغط على تصدير الفيديو' : 'Click Render Video'}</li>
          </ol>
        )}
      </div>

      {errorMsg && (
        <div className="bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400 border border-red-200 dark:border-red-900/50 px-4 py-3 rounded-xl text-sm font-mono flex items-center justify-between">
          <span>{errorMsg}</span>
          <button 
            onClick={() => window.location.reload()} 
            className="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 px-2 py-1 rounded text-xs text-slate-700 dark:text-slate-350 hover:text-slate-900 dark:hover:text-slate-100"
          >
            {t('common.retryConnection')}
          </button>
        </div>
      )}

      {loadingPrereqs ? (
        <div className="flex flex-col items-center justify-center min-h-[30vh]">
          <div className="w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full"></div>
          <span className="text-xs text-slate-500 dark:text-slate-400 mt-3 font-mono">{t('common.connectingToApi')}</span>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
          {/* Left panel: selection & config */}
          <div className="lg:col-span-7 flex flex-col gap-6">
            
            {/* Required Selections Grid: Reciter & Surah */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6">
              <div className="flex flex-col gap-4">
                <div>
                  <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                    {isAr ? 'القارئ' : 'Reciter'}
                  </h3>
                  <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 font-sans">
                    {isAr ? 'اختر القارئ للمصدر الصوتي والتوقيت.' : 'Choose the reciter for audio and timings.'}
                  </p>
                </div>
                <ReciterSelect
                  reciters={reciters}
                  selectedSlug={selectedReciter}
                  onSelect={setSelectedReciter}
                  loading={loadingStatus}
                />
              </div>

              <div className="flex flex-col gap-4 border-t md:border-t-0 md:border-l border-slate-100 dark:border-slate-800/60 pt-4 md:pt-0 md:pl-6 rtl:md:pl-0 rtl:md:pr-6">
                <div>
                  <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                    {isAr ? 'اختيار القرآن' : 'Quran Selection'}
                  </h3>
                  <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 font-sans">
                    {isAr ? 'اختر السورة ومدى الآيات لتصدير الفيديو.' : 'Select the Surah and Ayah range.'}
                  </p>
                </div>
                
                <div className="flex flex-col gap-4">
                  <SurahSelect
                    surahs={surahs}
                    selectedNumber={selectedSurah}
                    onSelect={setSelectedSurah}
                    loading={loadingStatus}
                  />

                  {selectedSurah !== '' && (
                    <div className="flex flex-col gap-3 border-t border-slate-100 dark:border-slate-800/60 pt-4">
                      <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        {isAr ? 'اختر النطاق' : 'Select Scope'}
                      </label>
                      <div className="grid grid-cols-3 gap-2 bg-slate-50 dark:bg-slate-950 p-1 rounded-xl border border-slate-200 dark:border-slate-800">
                        <button
                          type="button"
                          onClick={() => setScope('full')}
                          className={`py-1.5 px-3 rounded-lg text-xs font-semibold ${
                            scope === 'full'
                              ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-sm border border-slate-200 dark:border-slate-700'
                              : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'
                          }`}
                        >
                          {t('render.scopeFull')}
                        </button>
                        <button
                          type="button"
                          onClick={() => setScope('single')}
                          className={`py-1.5 px-3 rounded-lg text-xs font-semibold ${
                            scope === 'single'
                              ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-sm border border-slate-200 dark:border-slate-700'
                              : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'
                          }`}
                        >
                          {t('render.scopeSingle')}
                        </button>
                        <button
                          type="button"
                          onClick={() => setScope('range')}
                          className={`py-1.5 px-3 rounded-lg text-xs font-semibold ${
                            scope === 'range'
                              ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-sm border border-slate-200 dark:border-slate-700'
                              : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'
                          }`}
                        >
                          {t('render.scopeRange')}
                        </button>
                      </div>
                    </div>
                  )}

                  {/* Conditional Inputs */}
                  {selectedSurah !== '' && scope === 'single' && (
                    <div className="flex flex-col gap-1.5">
                      <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">{t('render.ayahNumberLabel', { max: maxAyahs })}</label>
                      <input
                        type="number"
                        min={1}
                        max={maxAyahs}
                        value={ayahNumber}
                        onChange={(e) => setAyahNumber(Math.max(1, Math.min(maxAyahs, Number(e.target.value))))}
                        className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-1.5 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 text-sm font-mono"
                      />
                    </div>
                  )}

                  {selectedSurah !== '' && scope === 'range' && (
                    <div className="grid grid-cols-2 gap-4">
                      <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">{t('render.fromLabel')}</label>
                        <input
                          type="number"
                          min={1}
                          max={toAyah}
                          value={fromAyah}
                          onChange={(e) => setFromAyah(Math.max(1, Math.min(toAyah, Number(e.target.value))))}
                          className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-1.5 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 text-sm font-mono"
                        />
                      </div>
                      <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">{t('render.toLabel', { max: maxAyahs })}</label>
                        <input
                          type="number"
                          min={fromAyah}
                          max={maxAyahs}
                          value={toAyah}
                          onChange={(e) => setToAyah(Math.max(fromAyah, Math.min(maxAyahs, Number(e.target.value))))}
                          className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-1.5 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 text-sm font-mono"
                        />
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Layout Selection Card */}
            <RenderOptions
              disabled={selectedSurah === ''}
              layout={layout}
              onLayoutChange={setLayout}
              maxLines={maxLines}
              onMaxLinesChange={setMaxLines}
              withTranslation={withTranslation}
              onWithTranslationChange={setWithTranslation}
              translationSource={translationSource}
              onTranslationSourceChange={setTranslationSource}
              withTafsir={withTafsir}
              onWithTafsirChange={setWithTafsir}
              useGeneratedContent={useGeneratedContent}
              onUseGeneratedContentChange={setUseGeneratedContent}
              persistToDatabase={persistToDatabase}
              onPersistToDatabaseChange={setPersistToDatabase}
              useQuranMeTheme={useQuranMeTheme}
              onUseQuranMeThemeChange={setUseQuranMeTheme}
            />

            {/* PROGRESSIVE DISCLOSURE: Render AI Operations Dynamically from Config */}
            {selectedReciter && selectedSurah !== '' && currentLayoutConfig?.availableAiTasks.map((taskId) => {
              const task = AI_TASKS[taskId];
              if (!task) return null;

              if (task.id === 'segmentation') {
                return (
                  <AITaskCard
                    key={task.id}
                    title={isAr ? task.titleAr : task.titleEn}
                    description={isAr ? task.descriptionAr : task.descriptionEn}
                    icon={task.icon}
                    requiresMarkers={task.requiresMarkers}
                    requiresMushafLineInfo={task.requiresMushafLineInfo}
                    startPage=""
                    startLine=""
                    markers=""
                    generatingPrompt={segGenerating}
                    generatedPrompt={segPrompt}
                    isCopied={segCopied}
                    onGeneratePrompt={runGenerateSegPrompt}
                    onCopyPrompt={copySegPrompt}
                    jsonInput={segJsonInput}
                    onJsonInputChange={setSegJsonInput}
                    isValidating={segValidating}
                    onValidateJson={runValidateSegJson}
                    validationSuccessMessage={segSuccessMsg}
                    validationErrors={segErrors}
                    validationWarnings={segWarnings}
                    errorMessage={segErrorMsg}
                    isSegmentation={true}
                    segmentsPreview={segPreview}
                    isRepaired={segRepaired}
                    saving={segSaving}
                    generatorType={segGenType}
                    generatorModel={segGenModel}
                    latencyMs={segLatencyMs}
                    onGeneratorTypeChange={setSegGenType}
                    onGeneratorModelChange={setSegGenModel}
                    onLatencyMsChange={setSegLatencyMs}
                    onApproveJson={runApproveSegJson}
                  />
                );
              }

              if (task.id === 'segment_timing') {
                return (
                  <AITaskCard
                    key={task.id}
                    title={isAr ? task.titleAr : task.titleEn}
                    description={isAr ? task.descriptionAr : task.descriptionEn}
                    icon={task.icon}
                    requiresMarkers={task.requiresMarkers}
                    requiresMushafLineInfo={task.requiresMushafLineInfo}
                    startPage=""
                    startLine=""
                    markers={segTimMarkers}
                    onMarkersChange={setSegTimMarkers}
                    generatingPrompt={segTimGenerating}
                    generatedPrompt={segTimPrompt}
                    isCopied={segTimCopied}
                    onGeneratePrompt={runGenerateSegTimPrompt}
                    onCopyPrompt={copySegTimPrompt}
                    jsonInput={segTimJsonInput}
                    onJsonInputChange={setSegTimJsonInput}
                    isValidating={segTimValidating}
                    onValidateJson={runImportSegTimJson}
                    validationSuccessMessage={segTimSuccessMsg}
                    validationErrors={segTimErrors}
                    validationWarnings={segTimWarnings}
                    errorMessage={segTimErrorMsg}
                    isSegmentation={false}
                    isSegmentTiming={true}
                    surahNumber={Number(selectedSurah)}
                    reciterSlug={selectedReciter}
                    scope={scope}
                    ayahNumber={ayahNumber}
                    fromAyah={fromAyah}
                    toAyah={toAyah}
                    approvedSegments={status?.approved_segments || []}
                    persistToDatabase={persistToDatabase}
                    onPersistToDatabaseChange={setPersistToDatabase}
                    onManualTimingsComplete={async (json, persist) => {
                      setSegTimJsonInput(json);
                      setLastSegTimJson(json);
                      setUseGeneratedContent(true);
                      const shouldPersist = persist !== undefined ? persist : persistToDatabase;
                      if (shouldPersist) {
                        await runImportSegTimJson(json);
                      } else {
                        setSegTimSuccessMsg(isAr ? 'تم حفظ التوقيت لجلسة العمل الحالية فقط (Run-only)' : 'Segment timings saved for current session only (Run-only)');
                      }
                    }}
                  />
                );
              }

              if (task.id === 'line_timing') {
                return (
                  <AITaskCard
                    key={task.id}
                    title={isAr ? task.titleAr : task.titleEn}
                    description={isAr ? task.descriptionAr : task.descriptionEn}
                    icon={task.icon}
                    requiresMarkers={task.requiresMarkers}
                    requiresMushafLineInfo={task.requiresMushafLineInfo}
                    startPage={lineTimStartPage}
                    startLine={lineTimStartLine}
                    markers={lineTimMarkers}
                    onStartPageChange={setLineTimStartPage}
                    onStartLineChange={setLineTimStartLine}
                    onMarkersChange={setLineTimMarkers}
                    generatingPrompt={lineTimGenerating}
                    generatedPrompt={lineTimPrompt}
                    isCopied={lineTimCopied}
                    onGeneratePrompt={runGenerateLineTimPrompt}
                    onCopyPrompt={copyLineTimPrompt}
                    jsonInput={lineTimJsonInput}
                    onJsonInputChange={setLineTimJsonInput}
                    isValidating={lineTimValidating}
                    onValidateJson={runImportLineTimJson}
                    validationSuccessMessage={lineTimSuccessMsg}
                    validationErrors={lineTimErrors}
                    validationWarnings={lineTimWarnings}
                    errorMessage={lineTimErrorMsg}
                    isSegmentation={false}
                  />
                );
              }

              return null;
            })}

            {/* Render Video Card (Always visible at the bottom when selection is active) */}
            {selectedReciter && selectedSurah !== '' && (
              <GenerateVideoCard
                surahNumber={selectedSurah}
                reciterSlug={selectedReciter}
                scope={scope}
                ayahNumber={ayahNumber}
                fromAyah={fromAyah}
                toAyah={toAyah}
                status={status}
                layout={layout}
                maxLines={maxLines}
                withTranslation={withTranslation}
                translationSource={translationSource}
                withTafsir={withTafsir}
                useGeneratedContent={useGeneratedContent}
                customJsonPayload={lastSegTimJson || undefined}
                useQuranMeTheme={useQuranMeTheme}
              />
            )}
          </div>

          {/* Right panel: status & actions */}
          {selectedReciter && selectedSurah !== '' && (
            <div className="lg:col-span-5 flex flex-col gap-6">
              <DatasetStatusCard
                status={status}
                loading={loadingStatus}
              />

              <DatasetActions
                onPrepare={handlePrepare}
                onUploadAudio={handleUploadAudio}
                onUploadTimings={handleUploadTimings}
                disabled={false}
                refreshing={loadingStatus}
                status={status}
              />
            </div>
          )}
        </div>
      )}
    </div>
  );
}
