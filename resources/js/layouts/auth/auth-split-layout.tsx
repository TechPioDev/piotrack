import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarCheck, LineChart, Sparkles } from 'lucide-react';

/**
 * The Piotrack auth experience: a committed dark-teal brand panel (the product
 * story, painted explicitly - it does not follow the OS theme) beside a calm
 * form column on the design-system ground. Every auth page inherits this
 * through AuthLayout.
 */

const PROOF_POINTS = [
    { icon: LineChart, text: 'SEO, ads and AI visibility measured to revenue - not vanity numbers.' },
    { icon: Sparkles, text: 'A chat assistant that qualifies visitors and answers from your own facts.' },
    { icon: CalendarCheck, text: 'Meetings booked straight into your pipeline, attributed end to end.' },
];

function BrandMark({ size = 'md' }: { size?: 'md' | 'lg' }) {
    return (
        <span
            className={`inline-grid shrink-0 place-items-center rounded-xl shadow-lg ${size === 'lg' ? 'size-12' : 'size-9'}`}
            style={{ background: 'linear-gradient(140deg, hsl(168 76% 42%), hsl(174 84% 24%))', boxShadow: '0 8px 20px -8px hsl(172 82% 37%)' }}
            aria-hidden
        >
            <AppLogoIcon className={size === 'lg' ? 'size-6 text-white' : 'size-5 text-white'} />
        </span>
    );
}

export default function AuthSplitLayout({ children, title, description }: { children: React.ReactNode; title?: string; description?: string }) {
    const { name, quote } = usePage<SharedData>().props;

    return (
        <div className="grid min-h-dvh lg:grid-cols-[minmax(0,44%)_minmax(0,56%)]">
            {/* Brand panel: one deliberate dark look, painted explicitly. */}
            <div
                className="relative hidden flex-col justify-between overflow-hidden p-10 lg:flex"
                style={{
                    background: 'linear-gradient(160deg, hsl(180 42% 7%) 0%, hsl(174 45% 11%) 55%, hsl(170 40% 15%) 100%)',
                    color: 'hsl(160 25% 96%)',
                }}
            >
                {/* The product's own motif: a rising line, drawn faint behind everything. */}
                <svg
                    className="pointer-events-none absolute inset-x-0 bottom-0 h-3/5 w-full"
                    viewBox="0 0 600 400"
                    preserveAspectRatio="none"
                    aria-hidden
                >
                    <path
                        d="M0,340 C120,330 170,290 260,270 C350,250 400,180 470,140 C520,110 560,80 600,60 L600,400 L0,400 Z"
                        fill="hsl(172 82% 37% / 0.10)"
                    />
                    <path
                        d="M0,340 C120,330 170,290 260,270 C350,250 400,180 470,140 C520,110 560,80 600,60"
                        fill="none"
                        stroke="hsl(172 70% 45% / 0.45)"
                        strokeWidth="2.5"
                    />
                    <circle cx="600" cy="60" r="5" fill="hsl(168 76% 48%)" />
                </svg>

                <Link href={route('home')} className="relative z-10 flex items-center gap-2.5 text-lg font-semibold tracking-tight">
                    <BrandMark />
                    {name}
                </Link>

                <div className="relative z-10 max-w-md">
                    <p className="text-xs font-semibold tracking-[0.18em] uppercase" style={{ color: 'hsl(168 60% 60%)' }}>
                        MSP Growth OS
                    </p>
                    <h2 className="mt-3 text-3xl leading-tight font-semibold text-balance" style={{ color: 'hsl(160 30% 98%)' }}>
                        Turn scattered marketing into predictable pipeline.
                    </h2>
                    <ul className="mt-8 space-y-4">
                        {PROOF_POINTS.map(({ icon: Icon, text }) => (
                            <li key={text} className="flex items-start gap-3 text-sm leading-relaxed" style={{ color: 'hsl(165 15% 78%)' }}>
                                <span
                                    className="mt-0.5 grid size-6 shrink-0 place-items-center rounded-md"
                                    style={{ background: 'hsl(172 60% 40% / 0.18)', color: 'hsl(168 70% 55%)' }}
                                    aria-hidden
                                >
                                    <Icon className="size-3.5" />
                                </span>
                                {text}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="relative z-10">
                    {quote && (
                        <blockquote className="max-w-md border-l-2 pl-4" style={{ borderColor: 'hsl(172 60% 40% / 0.5)' }}>
                            <p className="text-sm leading-relaxed" style={{ color: 'hsl(165 15% 72%)' }}>
                                &ldquo;{quote.message}&rdquo;
                            </p>
                            <footer className="mt-1.5 text-xs" style={{ color: 'hsl(165 12% 55%)' }}>
                                {quote.author}
                            </footer>
                        </blockquote>
                    )}
                </div>
            </div>

            {/* Form column on the design-system ground. */}
            <div className="bg-background flex flex-col px-6 py-8 sm:px-10">
                <div className="flex flex-1 items-center justify-center py-10">
                    <div className="w-full max-w-sm">
                        {/* The logo heads the form itself and always leads home. */}
                        <Link
                            href={route('home')}
                            aria-label={`${name} home`}
                            className="mb-8 inline-flex items-center gap-2.5 text-lg font-semibold tracking-tight"
                        >
                            <BrandMark size="lg" />
                            {name}
                        </Link>
                        <div className="mb-8">
                            <h1 className="text-2xl font-semibold tracking-tight text-balance">{title}</h1>
                            {description && <p className="text-muted-foreground mt-1.5 text-sm">{description}</p>}
                        </div>
                        {children}
                    </div>
                </div>

                <p className="text-muted-foreground text-center text-xs">
                    &copy; {new Date().getFullYear()} {name} · Growth software for managed service providers
                </p>
            </div>
        </div>
    );
}
