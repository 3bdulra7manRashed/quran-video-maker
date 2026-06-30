'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

interface NavigationProps {
  apiUrl: string;
  onApiUrlChange: (url: string) => void;
}

export default function Navigation({ apiUrl, onApiUrlChange }: NavigationProps) {
  const pathname = usePathname();

  const links = [
    { href: '/', label: 'Console', labelAr: 'لوحة التحكم' },
    { href: '/jobs', label: 'Jobs', labelAr: 'المهام' },
    { href: '/videos', label: 'Videos', labelAr: 'الفيديوهات' },
  ];

  return (
    <header className="border-b border-zinc-800 bg-zinc-950/80 backdrop-blur-sm sticky top-0 z-50 px-4 py-3 flex flex-wrap items-center justify-between gap-4">
      <div className="flex items-center gap-6">
        <Link href="/" className="flex items-center gap-2">
          <span className="text-xl font-bold bg-gradient-to-r from-emerald-400 to-teal-500 bg-clip-text text-transparent">
            Quran Video maker
          </span>
          <span className="text-xs bg-zinc-800 text-zinc-400 px-2 py-0.5 rounded font-mono">
            MVP
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
                <span className="mr-1.5">{link.label}</span>
                <span className="text-xs text-zinc-500 font-normal">({link.labelAr})</span>
              </Link>
            );
          })}
        </nav>
      </div>

      <div className="flex items-center gap-3 w-full sm:w-auto">
        <label className="text-xs text-zinc-500 font-mono whitespace-nowrap">API URL:</label>
        <input
          type="text"
          value={apiUrl}
          onChange={(e) => onApiUrlChange(e.target.value)}
          className="bg-zinc-900 border border-zinc-800 text-zinc-300 px-3 py-1.5 rounded-lg text-xs font-mono focus:outline-none focus:border-zinc-700 w-full sm:w-64 transition-colors"
          placeholder="http://localhost:8000"
        />
      </div>
    </header>
  );
}
