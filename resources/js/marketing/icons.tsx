import { type ReactNode } from 'react';

/**
 * Small stroke icon set for the marketing pages (MSITE) — same 24px/2px-stroke
 * language as the app's Lucide icons, inlined so the `.lp` pages stay
 * self-contained.
 */
const PATHS: Record<string, ReactNode> = {
    gauge: (
        <>
            <path d="M12 14l3.5-3.5" />
            <path d="M20.3 17.7A9 9 0 1 0 3.7 17.7" />
        </>
    ),
    users: (
        <>
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
        </>
    ),
    radar: (
        <>
            <path d="M19.07 4.93A10 10 0 0 0 6.99 3.34" />
            <path d="M4 6h.01M2.29 9.62a10 10 0 1 0 19.02-1.27" />
            <circle cx="12" cy="12" r="2" />
            <path d="M13.41 10.59l5.66-5.66" />
        </>
    ),
    mail: (
        <>
            <rect x="2" y="4" width="20" height="16" rx="2" />
            <path d="M22 7l-10 6L2 7" />
        </>
    ),
    funnel: <path d="M3 4h18l-7 8v6l-4 2v-8L3 4z" />,
    trend: (
        <>
            <path d="M3 17l5-5 4 3 8-9" />
            <path d="M15 6h5v5" />
        </>
    ),
    sparkle: (
        <>
            <path d="M12 3l1.9 5.7L19.6 10l-5.7 1.9L12 17.6l-1.9-5.7L4.4 10l5.7-1.9L12 3z" />
            <path d="M19 15l.9 2.6L22.5 18l-2.6.9L19 21.5l-.9-2.6L15.5 18l2.6-.9L19 15z" />
        </>
    ),
    chat: (
        <>
            <path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.6 8.6 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 1 1 16.1-3.8z" />
        </>
    ),
    bell: (
        <>
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.7 21a2 2 0 0 1-3.4 0" />
        </>
    ),
    shield: (
        <>
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            <path d="M9 12l2 2 4-4" />
        </>
    ),
    receipt: (
        <>
            <path d="M4 2v20l2-1.5L8 22l2-1.5L12 22l2-1.5L16 22l2-1.5L20 22V2l-2 1.5L16 2l-2 1.5L12 2l-2 1.5L8 2 6 3.5 4 2z" />
            <path d="M8 8h8M8 12h8M8 16h5" />
        </>
    ),
    calendar: (
        <>
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
            <path d="M9 16l2 2 4-4" />
        </>
    ),
    guide: (
        <>
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15z" />
            <path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5" />
        </>
    ),
    reply: (
        <>
            <path d="M9 17l-5-5 5-5" />
            <path d="M4 12h11a5 5 0 0 1 5 5v3" />
        </>
    ),
};

export type IconName = keyof typeof PATHS;

export function Icon({ name }: { name: IconName }) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            {PATHS[name]}
        </svg>
    );
}
