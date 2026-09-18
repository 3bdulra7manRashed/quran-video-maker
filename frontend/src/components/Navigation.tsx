'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import StudioLogo from './StudioLogo';
import { useLanguage } from '@/hooks/useLanguage';
import { useT } from '@/hooks/useT';

interface NavigationProps {
  apiUrl: string;
  onApiUrlChange: (url: string) => void;
}

export default function Navigation({ apiUrl, onApiUrlChange }: NavigationProps) {
  const pathname = usePathname();
  const { language, setLanguage } = useLanguage();
  const t = useT();
  const isAr = language === 'ar';

  const [showSettings, setShowSettings] = useState(false);
  const [tempUrl, setTempUrl] = useState(apiUrl);

  const links = [
    { href: '/', labelKey: 'common.nav.console' as const, icon: 'movie_edit' },
    { href: '/jobs', labelKey: 'common.nav.jobs' as const, icon: 'video_settings' },
    { href: '/videos', labelKey: 'common.nav.videos' as const, icon: 'video_library' },
  ];

  const handleSaveUrl = (e: React.FormEvent) => {
    e.preventDefault();
    onApiUrlChange(tempUrl);
    setShowSettings(false);
  };

  return (
    <>
      <header className="fixed top-0 left-0 right-0 z-50 h-16 bg-[#131a18]/95 backdrop-blur-xl border-b border-[#26332e] shadow-[0_2px_14px_rgba(0,0,0,0.5)]">
        <div className="h-16 w-full max-w-[1720px] mx-auto px-4 flex items-center justify-between gap-3">
          {/* Brand Logo and App Title */}
          <div className="flex items-center gap-3">
            <Link href="/" className="flex items-center gap-2.5 group">
              <div className="w-9 h-9 rounded-lg overflow-hidden flex items-center justify-center p-0.5 border border-[#d4af37]/40 bg-[#09100e] shadow-[0_0_12px_rgba(212,175,55,0.2)] group-hover:border-[#f2ca50] transition-colors">
                <StudioLogo size={32} />
              </div>
              <div className="flex flex-col">
                <div className="flex items-center gap-1.5">
                  <span className="text-[15px] font-bold text-[#dde4e0] tracking-tight group-hover:text-[#f2ca50] transition-colors">
                    {isAr ? 'استوديو فيديو القرآن الاحترافي' : 'Quran Video Studio'}
                  </span>
                  <span className="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-[#f2ca50]/15 text-[#f2ca50] border border-[#f2ca50]/30 uppercase">
                    v2.4 AI
                  </span>
                </div>
                <span className="text-[10px] text-[#94a3b8] hidden sm:inline">
                  {isAr ? 'محطة إنتاج وتوليد مقاطع الريلز القرآنية' : 'Quranic Reels Production Workbench'}
                </span>
              </div>
            </Link>
          </div>

          {/* Navigation Tabs */}
          <nav className="flex items-center gap-1 bg-[#09100e] p-1 rounded-xl border border-[#26332e]">
            {links.map((link) => {
              const isActive = pathname === link.href;
              return (
                <Link
                  key={link.href}
                  href={link.href}
                  className={`px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                    isActive
                      ? 'bg-[#242b29] text-[#f2ca50] shadow-[inset_0_1px_0_rgba(242,202,80,0.3)]'
                      : 'text-[#94a3b8] hover:text-[#dde4e0] hover:bg-[#1a211f]'
                  }`}
                >
                  <span className="material-symbols-outlined text-[16px]">{link.icon}</span>
                  <span>{t(link.labelKey)}</span>
                </Link>
              );
            })}
          </nav>

          {/* Controls: Server Status, Language, Settings */}
          <div className="flex items-center gap-2">
            {/* Server Status Indicator */}
            <div className="hidden md:flex items-center gap-1.5 bg-[#09100e] px-2.5 py-1 rounded-lg border border-[#26332e] text-[11px]">
              <span className="w-1.5 h-1.5 rounded-full bg-[#45dfa4] animate-pulse"></span>
              <span className="text-[#94a3b8]">{isAr ? 'الخادم:' : 'Server:'}</span>
              <span className="text-[#45dfa4] font-mono font-semibold">8000 (متصل)</span>
            </div>

            {/* Language Switcher */}
            <button
              type="button"
              onClick={() => setLanguage(isAr ? 'en' : 'ar')}
              className="px-2.5 py-1 rounded-lg bg-[#1a211f] hover:bg-[#242b29] text-[#94a3b8] hover:text-[#dde4e0] text-xs font-mono font-bold border border-[#26332e] transition-colors cursor-pointer"
              title="تبديل اللغة / Change Language"
            >
              {isAr ? 'EN' : 'عربي'}
            </button>

            {/* API Settings Modal Trigger */}
            <button
              type="button"
              onClick={() => {
                setTempUrl(apiUrl);
                setShowSettings(true);
              }}
              className="p-1.5 rounded-lg bg-[#1a211f] hover:bg-[#242b29] text-[#94a3b8] hover:text-[#dde4e0] border border-[#26332e] transition-colors cursor-pointer"
              title={isAr ? 'إعدادات عنوان الخادم' : 'Server URL Settings'}
            >
              <span className="material-symbols-outlined text-[18px]">settings</span>
            </button>
          </div>
        </div>
      </header>

      {/* Settings Modal */}
      {showSettings && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
          <div className="w-full max-w-md bg-[#131a18] border border-[#26332e] rounded-2xl p-6 shadow-2xl">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-sm font-bold text-[#dde4e0] flex items-center gap-2">
                <span className="material-symbols-outlined text-[#f2ca50]">settings</span>
                <span>{isAr ? 'إعدادات اتصال الخادم' : 'Server Connection Settings'}</span>
              </h3>
              <button
                type="button"
                onClick={() => setShowSettings(false)}
                className="text-[#94a3b8] hover:text-[#dde4e0]"
              >
                <span className="material-symbols-outlined text-[20px]">close</span>
              </button>
            </div>

            <form onSubmit={handleSaveUrl} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-[#94a3b8] mb-1.5">
                  {isAr ? 'عنوان خادم Laravel API:' : 'Laravel API Backend URL:'}
                </label>
                <input
                  type="text"
                  value={tempUrl}
                  onChange={(e) => setTempUrl(e.target.value)}
                  placeholder="http://localhost:8000"
                  className="w-full bg-[#09100e] border border-[#26332e] text-[#dde4e0] px-3 py-2 rounded-xl text-xs font-mono focus:border-[#f2ca50] focus:outline-none"
                  dir="ltr"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowSettings(false)}
                  className="px-4 py-2 rounded-xl bg-[#1a211f] hover:bg-[#242b29] text-[#94a3b8] text-xs font-medium"
                >
                  {isAr ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 rounded-xl bg-[#f2ca50] hover:bg-[#e5c365] text-[#3c2f00] text-xs font-bold shadow-md"
                >
                  {isAr ? 'حفظ الاتصال' : 'Save Connection'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}
