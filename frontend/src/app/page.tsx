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
import GenerateContentCard from '@/components/GenerateContentCard';
import GenerateMushafPromptCard from '@/components/GenerateMushafPromptCard';
import JSONInputCard from '@/components/JSONInputCard';
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

  const [jsonFile, setJsonFile] = useState<File | null>(null);
  const [jsonFileContent, setJsonFileContent] = useState<string>('');
  const [pastedJson, setPastedJson] = useState<string>('');

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

  // Translation states
  const [withTranslation, setWithTranslation] = useState<boolean>(false);
  const [translationSource, setTranslationSource] = useState<string>('sahih_international');

  // AI content states
  const [useGeneratedContent, setUseGeneratedContent] = useState<boolean>(false);

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
    }

    await api.prepareDataset(selectedReciter, selectedSurah);

    setLoadingStatus(true);
    let attempts = 0;
    const maxAttempts = 30; // 60 seconds max

    pollingIntervalRef.current = setInterval(async () => {
      attempts++;
      try {
        const stats = await api.getDatasetStatus(selectedReciter, selectedSurah);
        if (stats.renderable || attempts >= maxAttempts) {
          if (pollingIntervalRef.current) {
            clearInterval(pollingIntervalRef.current);
            pollingIntervalRef.current = null;
          }
          setStatus(stats);
          setLoadingStatus(false);
        } else {
          setStatus(stats);
        }
      } catch (err) {
        console.error('Polling status failed:', err);
        if (attempts >= maxAttempts) {
          if (pollingIntervalRef.current) {
            clearInterval(pollingIntervalRef.current);
            pollingIntervalRef.current = null;
          }
          setLoadingStatus(false);
        }
      }
    }, 2000);
  };

  const handleUploadAudio = async (file: File) => {
    if (!selectedReciter || selectedSurah === '') return;
    await api.uploadAudio(selectedReciter, selectedSurah, file);
    // Refresh status
    await loadDatasetStatus();
  };

  const handleUploadTimings = async (file: File) => {
    if (!selectedReciter) return;
    await api.uploadTimings(selectedReciter, file);
    // Refresh status
    await loadDatasetStatus();
  };

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

      {/* Onboarding Guide Card */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-xs flex flex-col gap-2">
        <h4 className="font-semibold text-slate-900 dark:text-slate-100">
          {isAr ? 'طريقة العمل' : 'How it works'}
        </h4>
        <ol className="list-decimal list-inside space-y-1 text-slate-500 dark:text-slate-400">
          <li>{isAr ? 'اختر القارئ' : 'Select a reciter'}</li>
          <li>{isAr ? 'اختر التخطيط' : 'Select a layout'}</li>
          <li>{isAr ? 'اختر السورة ومدى الآيات' : 'Choose Surah and Ayahs'}</li>
          <li>{isAr ? 'اختياري: ولد أو الصق ملف JSON' : 'Optionally generate or paste JSON'}</li>
          <li>{isAr ? 'اضغط على تصدير الفيديو' : 'Click Render Video'}</li>
        </ol>
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
            
            {/* Audio Source Card */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4">
              <div>
                <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                  {isAr ? 'المصدر الصوتي' : 'Audio Source'}
                </h3>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 font-sans">
                  {isAr ? 'اختر القارئ الذي سيتم استخدام صوته وتوقيتاته.' : 'Choose the reciter whose audio and timings will be used.'}
                </p>
              </div>
              <ReciterSelect
                reciters={reciters}
                selectedSlug={selectedReciter}
                onSelect={setSelectedReciter}
                loading={loadingStatus}
              />
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
              useGeneratedContent={useGeneratedContent}
              onUseGeneratedContentChange={setUseGeneratedContent}
            />

            {/* Quran Selection Card */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 flex flex-col gap-4">
              <div>
                <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                  {isAr ? 'اختيار القرآن' : 'Quran Selection'}
                </h3>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 font-sans">
                  {isAr ? 'اختر السورة ومدى الآيات لتصدير الفيديو.' : 'Select the Surah and Ayah range to render.'}
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
                        className={`py-2 px-3 rounded-lg text-xs font-semibold ${
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
                        className={`py-2 px-3 rounded-lg text-xs font-semibold ${
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
                        className={`py-2 px-3 rounded-lg text-xs font-semibold ${
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
                      className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 text-sm font-mono"
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
                        className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 text-sm font-mono"
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
                        className="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-2 rounded-xl focus:outline-none focus:border-slate-450 dark:focus:border-slate-700 text-sm font-mono"
                      />
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* AI Tools prompts */}
            <GenerateContentCard
              surahNumber={selectedSurah}
              reciterSlug={selectedReciter}
              scope={scope}
              ayahNumber={ayahNumber}
              fromAyah={fromAyah}
              toAyah={toAyah}
              layout={layout}
            />

            <GenerateMushafPromptCard
              surahNumber={selectedSurah}
              scope={scope}
              ayahNumber={ayahNumber}
              fromAyah={fromAyah}
              toAyah={toAyah}
              layout={layout}
            />

            {/* JSON Input Card */}
            {selectedSurah !== '' && selectedReciter && (
              <JSONInputCard
                jsonFile={jsonFile}
                onFileChange={(file, content) => {
                  setJsonFile(file);
                  setJsonFileContent(content);
                }}
                pastedJson={pastedJson}
                onPastedJsonChange={setPastedJson}
              />
            )}

            {/* Render Video Card */}
            <GenerateVideoCard
              surahNumber={selectedSurah}
              reciterSlug={selectedReciter}
              scope={scope}
              ayahNumber={ayahNumber}
              fromAyah={fromAyah}
              toAyah={toAyah}
              renderable={status?.renderable ?? false}
              layout={layout}
              maxLines={maxLines}
              withTranslation={withTranslation}
              translationSource={translationSource}
              useGeneratedContent={useGeneratedContent}
              customJsonPayload={jsonFileContent || pastedJson || undefined}
            />
          </div>

          {/* Right panel: status & actions */}
          <div className="lg:col-span-5 flex flex-col gap-6">
            <DatasetStatusCard
              status={status}
              loading={loadingStatus}
            />

            <DatasetActions
              onPrepare={handlePrepare}
              onUploadAudio={handleUploadAudio}
              onUploadTimings={handleUploadTimings}
              disabled={selectedSurah === '' || !selectedReciter}
              refreshing={loadingStatus}
            />
          </div>
        </div>
      )}
    </div>
  );
}
