import { Icon, type IconName } from '@/marketing/icons';
import { type ReactNode } from 'react';

/**
 * Hero banner building blocks for the marketing subpages (MSITE) — the same
 * floating product-panel scene the landing page opens with, composed per page.
 * Decorative product-UI illustration, so the whole scene is aria-hidden.
 */

export function HeroScene({ label, children, floats }: { label: string; children: ReactNode; floats?: ReactNode }) {
    return (
        <div className="scene" aria-hidden="true">
            <div className="blob" />
            <div className="panel">
                <div className="panel-top">
                    <i />
                    <i />
                    <i />
                    <span>{label}</span>
                </div>
                <div className="panel-body">{children}</div>
            </div>
            {floats}
        </div>
    );
}

export function PanelRow({
    icon,
    color,
    text,
    sub,
    amount,
    amountColor,
    dim,
}: {
    icon: IconName;
    color: 'teal' | 'coral' | 'amber' | 'navy';
    text: string;
    sub?: string;
    amount?: string;
    amountColor?: 'coral';
    dim?: boolean;
}) {
    return (
        <div className={dim ? 'prow dim' : 'prow'}>
            <span className={`pic ${color}`}>
                <Icon name={icon} />
            </span>
            <div>
                {text}
                {sub && <small>{sub}</small>}
            </div>
            {amount && <span className={amountColor === 'coral' ? 'amt coral' : 'amt'}>{amount}</span>}
        </div>
    );
}

export function PanelChart({ gradientId }: { gradientId: string }) {
    return (
        <div className="chart">
            <svg viewBox="0 0 320 128" preserveAspectRatio="none">
                <defs>
                    <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
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
                <path className="area" style={{ fill: `url(#${gradientId})` }} d="M8 96 L 72 84 L 140 72 L 208 50 L 300 26 L 300 128 L 8 128 Z" />
                <path className="line" d="M8 96 L 72 84 L 140 72 L 208 50 L 300 26" />
                <circle className="dot" cx="300" cy="26" r="6" />
            </svg>
        </div>
    );
}

export function Kpi({ label, value, tone }: { label: string; value: string; tone?: 'teal' | 'coral' }) {
    return (
        <div className="kpi">
            <div className="l">{label}</div>
            <div className={`v${tone ? ` ${tone}` : ''}`}>{value}</div>
        </div>
    );
}

export function Float({ pos, icon, text, sub }: { pos: 'f1' | 'f2' | 'f3'; icon: IconName; text: string; sub: string }) {
    return (
        <div className={`float ${pos}`}>
            <span className="ic">
                <Icon name={icon} />
            </span>
            <div>
                {text}
                <small>{sub}</small>
            </div>
        </div>
    );
}
