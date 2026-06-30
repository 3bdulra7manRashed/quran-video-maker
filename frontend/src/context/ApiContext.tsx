'use client';

import React, { createContext, useContext, useState, useEffect } from 'react';
import { ApiService } from '@/services/api';

interface ApiContextType {
  apiUrl: string;
  setApiUrl: (url: string) => void;
  api: ApiService;
}

const ApiContext = createContext<ApiContextType | undefined>(undefined);

export function ApiProvider({ children }: { children: React.ReactNode }) {
  const [apiUrl, setApiUrl] = useState<string>('http://localhost:8000');

  // Load from localStorage on mount if available
  useEffect(() => {
    const saved = localStorage.getItem('qvm_api_url');
    if (saved) {
      setApiUrl(saved);
    }
  }, []);

  const handleSetApiUrl = (url: string) => {
    setApiUrl(url);
    localStorage.setItem('qvm_api_url', url);
  };

  const api = new ApiService(apiUrl);

  return (
    <ApiContext.Provider value={{ apiUrl, setApiUrl: handleSetApiUrl, api }}>
      {children}
    </ApiContext.Provider>
  );
}

export function useApi() {
  const context = useContext(ApiContext);
  if (!context) {
    throw new Error('useApi must be used within an ApiProvider');
  }
  return context;
}
