import { useAppearance } from '@/hooks/use-appearance';
import { SiteFooter } from '@/marketing/site-footer';
import { styles } from '@/marketing/styles';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * The public marketing page — a modern, animated product landing page for
 * Piotrack, the growth OS for MSPs. This is deliberately vibrant and playful
 * (its job is to convert visitors); the authenticated app stays clean and
 * data-dense. Everything is scoped under `.lp` with `--lp-*` tokens so nothing
 * leaks into the rest of the app, and the display faces are self-hosted (see
 * app.css) so the page renders identically on the air-gapped server.
 */

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const { updateAppearance } = useAppearance();
    const rootRef = useRef<HTMLDivElement>(null);
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
        const root = rootRef.current;
        if (!root) {
            return;
        }

        // Reveal sections as they scroll into view. The hero load sequence is
        // pure CSS (see the stylesheet), so it needs no JS here.
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
        root.querySelectorAll<HTMLElement>('.reveal').forEach((el) => revealObserver.observe(el));

        // Count the headline stats up when they land on screen.
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const format = (value: number, decimals: number) =>
            value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

        const countObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    const el = entry.target as HTMLElement;
                    countObserver.unobserve(el);

                    const target = parseFloat(el.dataset.count ?? '0');
                    const decimals = parseInt(el.dataset.dec ?? '0', 10);
                    const prefix = el.dataset.prefix ?? '';
                    const suffix = el.dataset.suffix ?? '';

                    if (reduceMotion) {
                        el.textContent = prefix + format(target, decimals) + suffix;
                        return;
                    }

                    const duration = 1400;
                    const start = performance.now();
                    const tick = (now: number) => {
                        const progress = Math.min((now - start) / duration, 1);
                        const eased = 1 - Math.pow(1 - progress, 3);
                        el.textContent = prefix + format(target * eased, decimals) + suffix;
                        if (progress < 1) {
                            requestAnimationFrame(tick);
                        }
                    };
                    requestAnimationFrame(tick);
                });
            },
            { threshold: 0.4 },
        );
        root.querySelectorAll<HTMLElement>('[data-count]').forEach((el) => countObserver.observe(el));

        // Thicken the sticky nav border once the page is scrolled.
        const nav = root.querySelector<HTMLElement>('.nav');
        const onScroll = () => nav?.classList.toggle('scrolled', window.scrollY > 8);
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => {
            revealObserver.disconnect();
            countObserver.disconnect();
            window.removeEventListener('scroll', onScroll);
        };
    }, []);

    return (
        <>
            <Head title="Piotrack — the growth OS for MSPs" />
            <style>{styles}</style>

            <div className="lp" ref={rootRef}>
                <header className="nav">
                    <div className="wrap nav-inner">
                        <a className="brand" href="#top">
                            <span className="mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M3 17l5-5 4 3 8-9" />
                                    <path d="M15 6h5v5" />
                                </svg>
                            </span>
                            Piotrack
                        </a>
                        <nav className="nav-links">
                            <Link href="/features">Features</Link>
                            <Link href="/how-it-works">How it works</Link>
                            <Link href="/results">Results</Link>
                            <Link href="/faq">FAQ</Link>
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

                <main id="top">
                    <section className="hero">
                        <div className="wrap hero-grid">
                            <div className="hero-copy">
                                <span className="badge-row reveal">
                                    <span className="pill">MSP GROWTH OS</span>
                                    Marketing · Sales · AI visibility, unified
                                </span>
                                <h1 className="reveal">
                                    Turn scattered marketing into{' '}
                                    <span className="hl">
                                        predictable pipeline
                                        <svg viewBox="0 0 300 20" preserveAspectRatio="none" aria-hidden="true">
                                            <path d="M4 14 Q 80 4 150 10 T 296 6" />
                                        </svg>
                                    </span>
                                    .
                                </h1>
                                <p className="lead reveal">
                                    Piotrack is the growth platform built for MSPs — SEO, ads, content, CRM and AI visibility in one place, with
                                    revenue attribution that proves exactly what&apos;s working.
                                </p>
                                <div className="hero-cta reveal">
                                    <Link className="btn btn-primary" href={auth.user ? route('dashboard') : route('register')}>
                                        Start free
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.4"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M5 12h14M13 6l6 6-6 6" />
                                        </svg>
                                    </Link>
                                    <a className="btn btn-ghost" href="#proof">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                        See a live demo
                                    </a>
                                </div>
                                <p className="hero-note reveal">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2.6"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                    No credit card · Live demo data included
                                </p>
                            </div>

                            <div className="scene" aria-hidden="true">
                                <div className="blob" />

                                <div className="spark">
                                    <svg viewBox="0 0 60 60" fill="none">
                                        <circle cx="30" cy="30" r="26" fill="#FF6B54" />
                                        <path d="M30 16c6 3 9 8 9 15 0 3-1 6-3 8l-2-3-2 3-2-3-2 3-2-3-2 3c-2-2-3-5-3-8 0-7 3-12 9-15z" fill="#fff" />
                                        <circle cx="30" cy="27" r="3" fill="#FF6B54" />
                                    </svg>
                                </div>

                                <div className="panel">
                                    <div className="panel-top">
                                        <i />
                                        <i />
                                        <i />
                                        <span>piotrack · growth</span>
                                    </div>
                                    <div className="panel-body">
                                        <div className="kpi-row">
                                            <div className="kpi">
                                                <div className="l">Pipeline</div>
                                                <div className="v teal">$486K</div>
                                            </div>
                                            <div className="kpi">
                                                <div className="l">New MRR</div>
                                                <div className="v">$31.4K</div>
                                            </div>
                                            <div className="kpi">
                                                <div className="l">ROAS</div>
                                                <div className="v coral">4.57x</div>
                                            </div>
                                        </div>
                                        <div className="chart">
                                            <svg viewBox="0 0 320 128" preserveAspectRatio="none">
                                                <defs>
                                                    <linearGradient id="lpAreaFill" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="0%" stopColor="var(--lp-teal)" stopOpacity="0.28" />
                                                        <stop offset="100%" stopColor="var(--lp-teal)" stopOpacity="0" />
                                                    </linearGradient>
                                                </defs>
                                                <g className="grid">
                                                    <line x1="0" y1="32" x2="320" y2="32" />
                                                    <line x1="0" y1="64" x2="320" y2="64" />
                                                    <line x1="0" y1="96" x2="320" y2="96" />
                                                </g>
                                                <g className="bars">
                                                    <rect x="18" y="78" width="20" height="46" rx="4" />
                                                    <rect x="78" y="66" width="20" height="58" rx="4" />
                                                    <rect x="138" y="72" width="20" height="52" rx="4" />
                                                    <rect x="198" y="48" width="20" height="76" rx="4" />
                                                    <rect x="258" y="30" width="20" height="94" rx="4" />
                                                </g>
                                                <path className="area" d="M8 96 L 72 84 L 140 72 L 208 50 L 300 26 L 300 128 L 8 128 Z" />
                                                <path className="line" d="M8 96 L 72 84 L 140 72 L 208 50 L 300 26" />
                                                <circle className="dot" cx="300" cy="26" r="6" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <div className="float f1">
                                    <span className="ic">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 2l2.4 7.4H22l-6 4.4 2.3 7.2-6.3-4.6L5.7 21l2.3-7.2-6-4.4h7.6z" />
                                        </svg>
                                    </span>
                                    <div>
                                        Hot lead 🔥<small>Michael · Precision Mfg</small>
                                    </div>
                                </div>
                                <div className="float f2">
                                    <span className="ic">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                                        </svg>
                                    </span>
                                    <div>
                                        +$4,500 MRR<small>Closed won · attributed</small>
                                    </div>
                                </div>
                                <div className="float f3">
                                    <span className="ic">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 2a10 10 0 1 0 10 10" />
                                            <path d="M12 6a6 6 0 1 0 6 6" />
                                            <circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none" />
                                        </svg>
                                    </span>
                                    <div>
                                        #2 in ChatGPT<small>&quot;Best MSP Philadelphia&quot;</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="trust">
                        <div className="wrap trust-inner reveal">
                            <span>Built for</span>
                            <span className="dot" />
                            <span>Managed IT</span>
                            <span className="dot" />
                            <span>Cybersecurity</span>
                            <span className="dot" />
                            <span>Microsoft 365</span>
                            <span className="dot" />
                            <span>Co-managed IT</span>
                            <span className="dot" />
                            <span>CMMC &amp; compliance</span>
                        </div>
                    </section>

                    <section className="stats" id="proof">
                        <div className="wrap">
                            <div className="stats-grid reveal">
                                <div className="stat">
                                    <div className="num" data-count="4.57" data-suffix="x" data-dec="2">
                                        0
                                    </div>
                                    <div className="cap">Return on ad spend</div>
                                </div>
                                <div className="stat">
                                    <div className="num" data-count="54" data-prefix="$" data-suffix="K">
                                        0
                                    </div>
                                    <div className="cap">ARR attributed, live</div>
                                </div>
                                <div className="stat">
                                    <div className="num" data-count="9">
                                        0
                                    </div>
                                    <div className="cap">Growth channels, unified</div>
                                </div>
                                <div className="stat">
                                    <div className="num" data-count="1142">
                                        0
                                    </div>
                                    <div className="cap">Features, one login</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="sec" id="features">
                        <div className="wrap">
                            <div className="sec-head reveal">
                                <span className="eyebrow">One platform</span>
                                <h2>Every growth channel, talking to each other.</h2>
                                <p>
                                    Stop stitching together six tools that never agree. Piotrack runs the whole funnel and connects every dollar back
                                    to the campaign that earned it.
                                </p>
                            </div>
                            <div className="features">
                                <article className="card reveal">
                                    <div className="ico c-teal">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M3 3v18h18" />
                                            <path d="M7 15l4-5 3 3 5-7" />
                                        </svg>
                                    </div>
                                    <h3>Revenue attribution</h3>
                                    <p>
                                        Trace a closed deal back through the meeting, the lead, the campaign and the keyword that started it. See real
                                        ROAS, CAC and pipeline — not vanity clicks.
                                    </p>
                                    <span className="tag">source → $ won</span>
                                </article>
                                <article className="card reveal">
                                    <div className="ico c-coral">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 3a9 9 0 1 0 9 9" />
                                            <path d="M12 7a5 5 0 1 0 5 5" />
                                            <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" />
                                        </svg>
                                    </div>
                                    <h3>AI visibility</h3>
                                    <p>
                                        Track how ChatGPT, Gemini and Perplexity answer &quot;best MSP near me&quot; — your mentions, citations and
                                        share of voice against competitors, over time.
                                    </p>
                                    <span className="tag">answer-engine ranking</span>
                                </article>
                                <article className="card reveal">
                                    <div className="ico c-amber">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                            <circle cx="9" cy="7" r="4" />
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 16 11" />
                                        </svg>
                                    </div>
                                    <h3>CRM &amp; pipeline</h3>
                                    <p>
                                        Contacts, companies, a real kanban pipeline and lead scoring that turns hot into a sales alert the moment it
                                        happens — no data-entry busywork.
                                    </p>
                                    <span className="tag">lead → close</span>
                                </article>
                                <article className="card reveal">
                                    <div className="ico c-cyan">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <circle cx="11" cy="11" r="8" />
                                            <path d="M21 21l-4.3-4.3" />
                                        </svg>
                                    </div>
                                    <h3>SEO &amp; local</h3>
                                    <p>
                                        Rank tracking, technical audits, keyword clusters and per-location visibility for every branch you serve —
                                        with recommendations you can act on today.
                                    </p>
                                    <span className="tag">rankings + audits</span>
                                </article>
                                <article className="card reveal">
                                    <div className="ico c-navy">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M22 2L11 13" />
                                            <path d="M22 2l-7 20-4-9-9-4 20-7z" />
                                        </svg>
                                    </div>
                                    <h3>Campaigns &amp; automation</h3>
                                    <p>
                                        Landing pages, forms, email and multi-step nurture workflows that fire on real behavior — every send
                                        suppression-safe and tracked end to end.
                                    </p>
                                    <span className="tag">nurture on autopilot</span>
                                </article>
                                <article className="card reveal">
                                    <div className="ico c-pink">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 2l2.4 7.4H22l-6 4.4 2.3 7.2-6.3-4.6L5.7 21l2.3-7.2-6-4.4h7.6z" />
                                        </svg>
                                    </div>
                                    <h3>Growth score</h3>
                                    <p>
                                        One number that grades your whole growth engine across ten weighted areas — and tells you the single weakest
                                        link to fix next.
                                    </p>
                                    <span className="tag">0–100, benchmarked</span>
                                </article>
                            </div>
                        </div>
                    </section>

                    <section className="sec" id="how" style={{ paddingTop: 0 }}>
                        <div className="wrap">
                            <div className="sec-head center reveal">
                                <span className="eyebrow">How it works</span>
                                <h2>From first click to closed deal — one thread.</h2>
                            </div>
                            <div className="flow">
                                <div className="step reveal">
                                    <span className="arrow">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.4"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M5 12h14M13 6l6 6-6 6" />
                                        </svg>
                                    </span>
                                    <div className="n" />
                                    <h3>Capture</h3>
                                    <p>
                                        A visitor finds you through search, ads or an AI answer, lands on a Piotrack page and becomes a scored lead in
                                        your CRM automatically.
                                    </p>
                                </div>
                                <div className="step reveal">
                                    <span className="arrow">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.4"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M5 12h14M13 6l6 6-6 6" />
                                        </svg>
                                    </span>
                                    <div className="n" />
                                    <h3>Qualify</h3>
                                    <p>
                                        Scoring and buyer-intent signals promote the lead to sales-ready and fire an alert, so your rep reaches the
                                        hot ones while they&apos;re still warm.
                                    </p>
                                </div>
                                <div className="step reveal">
                                    <div className="n" />
                                    <h3>Attribute</h3>
                                    <p>
                                        The deal closes and the revenue snaps back to the exact source — proving what to double down on and what to
                                        stop paying for.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="wrap" id="cta">
                        <div className="cta-band reveal">
                            <h2>See your growth engine in one place.</h2>
                            <p>
                                Spin up a workspace loaded with live demo data and click through the whole funnel — pipeline, campaigns, attribution
                                and AI visibility — in minutes.
                            </p>
                            <div className="hero-cta" style={{ justifyContent: 'center' }}>
                                <Link className="btn btn-primary" href={auth.user ? route('dashboard') : route('register')}>
                                    Start free
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2.4"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M5 12h14M13 6l6 6-6 6" />
                                    </svg>
                                </Link>
                                <Link className="btn btn-ghost" href={route('login')}>
                                    Log in
                                </Link>
                            </div>
                        </div>
                    </section>
                </main>

                <SiteFooter />
            </div>
        </>
    );
}
