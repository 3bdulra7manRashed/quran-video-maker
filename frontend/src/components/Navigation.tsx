'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
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

  const links = [
    { href: '/', labelKey: 'common.nav.console' as const },
    { href: '/jobs', labelKey: 'common.nav.jobs' as const },
    { href: '/videos', labelKey: 'common.nav.videos' as const },
  ];

  return (
    <header className="border-b border-zinc-800 bg-zinc-950/80 backdrop-blur-sm sticky top-0 z-50 px-4 py-3 flex flex-wrap items-center justify-between gap-4">
      <div className="flex items-center gap-6">
        <Link href="/" className="flex items-center gap-2">
          <span className="text-xl font-bold bg-gradient-to-r from-emerald-400 to-teal-500 bg-clip-text text-transparent">
            {t('common.appName')}
          </span>
          <span className="text-xs bg-zinc-800 text-zinc-400 px-2 py-0.5 rounded font-mono">
            {t('common.mvp')}
          </span>
        </Link>

        <nav className="flex items-center gap-1">
          {links.map((link) => {
            const isActive = pathname === link.href;
            return (
              <Link
                key={link.href}
                href={link.href}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-zinc-800 text-emerald-400 font-semibold'
                    : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900/50'
                }`}
              >
                <span>{t(link.labelKey)}</span>
              </Link>
            );
          })}
        </nav>
      </div>

      <div className="flex items-center gap-4 w-full sm:w-auto flex-wrap sm:flex-nowrap">
        <div className="flex items-center gap-2">
          <label className="text-xs text-zinc-500 font-mono whitespace-nowrap">{t('settings.language')}:</label>
          <select
            value={language}
            onChange={(e) => setLanguage(e.target.value as any)}
            className="bg-zinc-900 border border-zinc-800 text-zinc-300 px-2.5 py-1.5 rounded-lg text-xs font-sans focus:outline-none focus:border-zinc-700 cursor-pointer"
          >
            <option value="ar">{t('settings.arabic')}</option>
            <option value="en">{t('settings.english')}</option>
          </select>
        </div>

        <div className="flex items-center gap-2 w-full sm:w-auto">
          <label className="text-xs text-zinc-500 font-mono whitespace-nowrap">{t('settings.apiUrl')}:</label>
          <input
            type="text"
            value={apiUrl}
            onChange={(e) => onApiUrlChange(e.target.value)}
            className="bg-zinc-900 border border-zinc-800 text-zinc-300 px-3 py-1.5 rounded-lg text-xs font-mono focus:outline-none focus:border-zinc-700 w-full sm:w-64 transition-colors"
            placeholder="http://localhost:8000"
          />
        </div>
      </div>
    </header>
  );
}
