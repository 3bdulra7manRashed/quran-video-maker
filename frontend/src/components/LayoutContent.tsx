'use client';

import React from 'react';
import Navigation from './Navigation';
import { useApi } from '@/context/ApiContext';

export default function LayoutContent({ children }: { children: React.ReactNode }) {
  const { apiUrl, setApiUrl } = useApi();

  return (
    <>
      <Navigation apiUrl={apiUrl} onApiUrlChange={setApiUrl} />
      <main className="flex-1 w-full max-w-7xl mx-auto px-4 pt-24 pb-12">
        {children}
      </main>
    </>
  );
}
