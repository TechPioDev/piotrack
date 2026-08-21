import { cn } from '@/lib/utils';
import { type LucideIcon, TrendingDown, TrendingUp } from 'lucide-react';

/**
 * A single KPI tile (audit §18).
 *
 * Dashboards answer "what happened" with a row of these: a label, the value,
 * and an optional period-over-period delta whose colour carries meaning
 * (green up, red down) rather than decoration. Formalises the ad-hoc Card + Badge
 * compositions the dashboards each built by hand.
 */
export function StatCard({
    label,
    value,
    delta,
    icon: Icon,
    className,
}: {
    label: string;
    value: string | number;
    delta?: { value: string; direction: 'up' | 'down' | 'neutral' };
    icon?: LucideIcon;
    className?: string;
}) {
    const DeltaIcon = delta?.direction === 'down' ? TrendingDown : TrendingUp;

    return (
        <div
            className={cn(
                'group border-border bg-card rounded-lg border p-4 transition duration-200',
                'hover:border-brand/50 hover:-translate-y-0.5 hover:shadow-md',
                'motion-reduce:transform-none motion-reduce:transition-none',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground text-sm font-medium">{label}</span>
                {Icon && (
                    <span className="bg-brand-soft text-brand-strong flex size-8 items-center justify-center rounded-lg transition duration-200 group-hover:scale-105 motion-reduce:transform-none">
                        <Icon className="size-4" aria-hidden />
                    </span>
                )}
            </div>
            <div className="mt-3 flex items-baseline gap-2">
                <span className="text-foreground text-3xl font-semibold tracking-tight tabular-nums">{value}</span>
                {delta && (
                    <span
                        className={cn(
                            'inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-semibold',
                            delta.direction === 'up' && 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
                            delta.direction === 'down' && 'bg-red-500/10 text-red-600 dark:text-red-400',
                            delta.direction === 'neutral' && 'text-muted-foreground',
                        )}
                    >
                        {delta.direction !== 'neutral' && <DeltaIcon className="size-3" aria-hidden />}
                        {delta.value}
                    </span>
                )}
            </div>
        </div>
    );
}
