'use client';

import React, { useEffect, useState, useRef } from 'react';
import { useApi } from '@/context/ApiContext';
import { Reciter, Surah, DatasetStatus } from '@/services/api';
import ReciterSelect from '@/components/ReciterSelect';
import SurahSelect from '@/components/SurahSelect';
import DatasetStatusCard from '@/components/DatasetStatusCard';
import DatasetActions from '@/components/DatasetActions';
import RenderOptions, { RenderScope } from '@/components/RenderOptions';
import GenerateVideoCard from '@/components/GenerateVideoCard';
import GenerateContentCard from '@/components/GenerateContentCard';
import { useT } from '@/hooks/useT';

export default function ConsolePage() {
  const { api, apiUrl } = useApi();
  const t = useT();

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
      <div className="border-b border-zinc-900 pb-4">
        <h1 className="text-2xl font-extrabold text-zinc-150 flex items-center justify-between">
          <span>{t('render.pageTitle')}</span>
        </h1>
        <p className="text-xs text-zinc-500 mt-1">
          {t('render.pageDescription')}
        </p>
      </div>

      {errorMsg && (
        <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl text-sm font-mono flex items-center justify-between">
          <span>{errorMsg}</span>
          <button 
            onClick={() => window.location.reload()} 
            className="bg-zinc-900 border border-zinc-800 px-2 py-1 rounded text-xs text-zinc-300 hover:text-zinc-100"
          >
            {t('common.retryConnection')}
          </button>
        </div>
      )}

      {loadingPrereqs ? (
        <div className="flex flex-col items-center justify-center min-h-[30vh]">
          <div className="w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
          <span className="text-xs text-zinc-500 mt-3 font-mono">{t('common.connectingToApi')}</span>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
          {/* Left panel: selection & config */}
          <div className="lg:col-span-7 flex flex-col gap-6">
            <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 flex flex-col gap-5 shadow-2xl">
              <h3 className="font-semibold text-zinc-200 border-b border-zinc-900 pb-2">{t('render.targetSelection')}</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <ReciterSelect
                  reciters={reciters}
                  selectedSlug={selectedReciter}
                  onSelect={setSelectedReciter}
                  loading={loadingStatus}
                />
                <SurahSelect
                  surahs={surahs}
                  selectedNumber={selectedSurah}
                  onSelect={setSelectedSurah}
                  loading={loadingStatus}
                />
              </div>
            </div>

            <RenderOptions
              scope={scope}
              onScopeChange={setScope}
              ayahNumber={ayahNumber}
              onAyahNumberChange={setAyahNumber}
              fromAyah={fromAyah}
              onFromAyahChange={setFromAyah}
              toAyah={toAyah}
              onToAyahChange={setToAyah}
              maxAyahs={maxAyahs}
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
            />

            <GenerateContentCard
              surahNumber={selectedSurah}
              reciterSlug={selectedReciter}
              scope={scope}
              ayahNumber={ayahNumber}
              fromAyah={fromAyah}
              toAyah={toAyah}
              layout={layout}
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
