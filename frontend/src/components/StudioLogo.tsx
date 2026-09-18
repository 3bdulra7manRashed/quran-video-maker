import React from 'react';

interface StudioLogoProps {
  className?: string;
  size?: number;
}

export default function StudioLogo({ className = "w-8 h-8", size = 32 }: StudioLogoProps) {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 100 100"
      fill="none"
      width={size}
      height={size}
      className={className}
    >
      <circle cx="50" cy="50" r="46" stroke="#D4AF37" strokeWidth="2" strokeOpacity="0.4" strokeDasharray="3 3" />
      <circle cx="50" cy="50" r="40" fill="#131A18" stroke="#26332E" strokeWidth="1.5" />
      <path
        d="M54 26C40.7452 26 30 36.7452 30 50C30 63.2548 40.7452 74 54 74C59.6433 74 64.8157 72.0463 68.9056 68.7844C61.4287 68.3242 55.4545 62.1332 55.4545 54.5455C55.4545 46.9577 61.4287 40.7667 68.9056 40.3065C64.8157 37.0445 59.6433 35.0909 54 35.0909"
        fill="url(#gold_grad)"
      />
      <polygon points="46,42 62,50 46,58" fill="#D4AF37" opacity="0.95" />
      <circle cx="50" cy="22" r="3" fill="#E5C365" />
      <circle cx="50" cy="78" r="3" fill="#E5C365" />
      <defs>
        <linearGradient id="gold_grad" x1="30" y1="26" x2="70" y2="74" gradientUnits="userSpaceOnUse">
          <stop stopColor="#F3E5AB" />
          <stop offset="0.5" stopColor="#D4AF37" />
          <stop offset="1" stopColor="#AA820A" />
        </linearGradient>
      </defs>
    </svg>
  );
}
