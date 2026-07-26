'use client';

import React, { createContext, useContext, useState, useEffect } from 'react';
import { ApiService } from '@/services/api';

interface ApiContextType {
  apiUrl: string;
  setApiUrl: (url: string) => void;
  api: ApiService;
}

const ApiContext = createContext<ApiContextType | undefined>(undefined);

const DEFAULT_API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001';

export function ApiProvider({ children }: { children: React.ReactNode }) {
  const [apiUrl, setApiUrl] = useState<string>(DEFAULT_API_URL);

  // Load from localStorage on mount if available
  useEffect(() => {
    const saved = localStorage.getItem('qvm_api_url');
    if (saved) {
      if (saved === 'http://localhost:8000' && DEFAULT_API_URL !== 'http://localhost:8000') {
        setApiUrl(DEFAULT_API_URL);
        localStorage.setItem('qvm_api_url', DEFAULT_API_URL);
      } else {
        setApiUrl(saved);
      }
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
