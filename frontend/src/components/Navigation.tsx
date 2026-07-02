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
    <header className="border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3 flex flex-wrap items-center justify-between gap-4">
      <div className="flex items-center gap-6">
        <Link href="/" className="flex items-center gap-2">
          <span className="text-xl font-bold text-slate-900 dark:text-slate-100">
            {t('common.appName')}
          </span>
        </Link>

        <nav className="flex items-center gap-1">
          {links.map((link) => {
            const isActive = pathname === link.href;
            return (
              <Link
                key={link.href}
                href={link.href}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium ${
                  isActive
                    ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-slate-100 font-semibold'
                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'
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
          <label className="text-xs text-slate-500 dark:text-slate-400 font-mono whitespace-nowrap">{t('settings.language')}:</label>
          <select
            value={language}
            onChange={(e) => setLanguage(e.target.value as any)}
            className="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-2.5 py-1.5 rounded-lg text-xs font-sans focus:outline-none focus:border-slate-400 dark:focus:border-slate-650 cursor-pointer"
          >
            <option value="ar">{t('settings.arabic')}</option>
            <option value="en">{t('settings.english')}</option>
          </select>
        </div>

        <div className="flex items-center gap-2 w-full sm:w-auto">
          <label className="text-xs text-slate-500 dark:text-slate-400 font-mono whitespace-nowrap">{t('settings.apiUrl')}:</label>
          <input
            type="text"
            value={apiUrl}
            onChange={(e) => onApiUrlChange(e.target.value)}
            className="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 px-3 py-1.5 rounded-lg text-xs font-mono focus:outline-none focus:border-slate-400 dark:focus:border-slate-650 w-full sm:w-64"
            placeholder="http://localhost:8000"
          />
        </div>
      </div>
    </header>
  );
}
