import { useAppearance } from '@/hooks/use-appearance';
import { SiteFooter } from '@/marketing/site-footer';
import { styles } from '@/marketing/styles';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { type ReactNode, useEffect, useState } from 'react';

const NAV_LINKS = [
    { href: '/features', label: 'Features' },
    { href: '/how-it-works', label: 'How it works' },
    { href: '/results', label: 'Results' },
    { href: '/faq', label: 'FAQ' },
    { href: '/contact', label: 'Contact' },
];

interface MarketingLayoutProps {
    /** Client-side <title> for SPA visits; the crawler title/meta/JSON-LD render server-side via Blade view data. */
    title: string;
    /** Canonical path of this page, e.g. "/features" — marks the active nav link. */
    path: string;
    children: ReactNode;
}

/**
 * Shared shell for the marketing subpages (MSITE): the landing page's `.lp`
 * design system, a sticky nav linking the public pages, and the shared footer
 * with the newsletter list.
 */
export default function MarketingLayout({ title, path, children }: MarketingLayoutProps) {
    const { auth } = usePage<SharedData>().props;
    const { updateAppearance } = useAppearance();
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        setIsDark(document.documentElement.classList.contains('dark'));
    }, []);

    const toggleTheme = () => {
        const nowDark = document.documentElement.classList.contains('dark');
        updateAppearance(nowDark ? 'light' : 'dark');
        setIsDark(!nowDark);
    };

    useEffect(() => {
        const nav = document.querySelector<HTMLElement>('.lp .nav');
        const onScroll = () => nav?.classList.toggle('scrolled', window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        // Same scroll-reveal treatment as the landing page.
        const revealObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in');
                        revealObserver.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.14 },
        );
        document.querySelectorAll<HTMLElement>('.lp .reveal').forEach((el) => revealObserver.observe(el));

        return () => {
            window.removeEventListener('scroll', onScroll);
            revealObserver.disconnect();
        };
    }, []);

    return (
        <>
            <Head title={title} />
            <style>{styles}</style>

            <div className="lp">
                <header className="nav">
                    <div className="wrap nav-inner">
                        <Link className="brand" href={route('home')} aria-label="Piotrack home">
                            <span className="mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M3 17l5-5 4 3 8-9" />
                                    <path d="M15 6h5v5" />
                                </svg>
                            </span>
                            Piotrack
                        </Link>
                        <nav className="nav-links" aria-label="Main">
                            {NAV_LINKS.map((link) => (
                                <Link key={link.href} href={link.href} aria-current={link.href === path ? 'page' : undefined}>
                                    {link.label}
                                </Link>
                            ))}
                        </nav>
                        <div className="nav-cta">
                            <button className="theme-toggle" onClick={toggleTheme} aria-label="Toggle theme" title="Toggle theme" type="button">
                                {isDark ? (
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
                                    </svg>
                                ) : (
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <circle cx="12" cy="12" r="4" />
                                        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
                                    </svg>
                                )}
                            </button>
                            {auth.user ? (
                                <Link className="btn btn-primary" href={route('dashboard')}>
                                    Go to dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link className="login-link" href={route('login')}>
                                        Log in
                                    </Link>
                                    <Link className="btn btn-primary" href={route('register')}>
                                        Start free
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <main>{children}</main>

                <SiteFooter />
            </div>
        </>
    );
}
