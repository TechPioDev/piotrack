import { cn } from '@/lib/utils';

/**
 * Lifecycle and temperature pills (CRMT module). Color carries the funnel
 * position — cool early, brand mid-funnel, green at customer — so a table
 * scan shows the mix without reading every word.
 */
const STAGE_STYLES: Record<string, string> = {
    subscriber: 'bg-muted text-muted-foreground',
    lead: 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
    mql: 'bg-indigo-500/12 text-indigo-700 dark:text-indigo-300',
    sql: 'bg-brand-soft text-brand-strong',
    opportunity: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    customer: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
    evangelist: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
};

export function StagePill({ stage, className }: { stage: string | null; className?: string }) {
    if (!stage) {
        return <span className="text-muted-foreground text-sm">—</span>;
    }

    return (
        <span
            className={cn(
                'inline-block rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap uppercase',
                STAGE_STYLES[stage] ?? 'bg-muted text-muted-foreground',
                className,
            )}
        >
            {stage.replace(/_/g, ' ')}
        </span>
    );
}

const TEMPERATURE_STYLES: Record<string, string> = {
    hot: 'text-red-600 dark:text-red-400',
    warm: 'text-amber-600 dark:text-amber-400',
    cold: 'text-muted-foreground',
};

export function ScoreCell({ score, temperature }: { score: number; temperature: string }) {
    return (
        <span className="inline-flex items-baseline gap-1.5 tabular-nums">
            <span className="text-foreground text-sm font-medium">{score}</span>
            <span className={cn('text-xs font-semibold uppercase', TEMPERATURE_STYLES[temperature] ?? 'text-muted-foreground')}>{temperature}</span>
        </span>
    );
}

const LEAD_STATUS_STYLES: Record<string, string> = {
    new: 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
    working: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    qualified: 'bg-brand-soft text-brand-strong',
    unqualified: 'bg-muted text-muted-foreground',
    converted: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
};

export function LeadStatusPill({ status }: { status: string }) {
    return (
        <span
            className={cn(
                'inline-block rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap uppercase',
                LEAD_STATUS_STYLES[status] ?? 'bg-muted text-muted-foreground',
            )}
        >
            {status}
        </span>
    );
}
