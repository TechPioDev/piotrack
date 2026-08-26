import { cn } from '@/lib/utils';

export type BarItem = { label: string; value: number; hint?: string };

/**
 * Labeled horizontal comparison (design-shell module): channels, campaigns,
 * stages — anywhere the question is "how do these compare?". Bars scale to
 * the largest value; numbers stay tabular so columns of digits line up.
 */
export function BarList({
    items,
    color = 'var(--chart-1)',
    formatValue = (v: number) => String(v),
    emptyText = 'Nothing to compare yet.',
    ariaLabel,
    className,
}: {
    items: BarItem[];
    color?: string;
    formatValue?: (value: number) => string;
    emptyText?: string;
    ariaLabel: string;
    className?: string;
}) {
    const max = Math.max(...items.map((i) => i.value), 0);

    if (items.length === 0 || max === 0) {
        return (
            <p className={cn('text-muted-foreground py-6 text-center text-sm', className)} role="img" aria-label={`${ariaLabel}: no data`}>
                {emptyText}
            </p>
        );
    }

    return (
        <div className={cn('space-y-2.5', className)} role="img" aria-label={ariaLabel}>
            {items.map((item) => (
                <div key={item.label} className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1">
                    <div className="min-w-0">
                        <div className="flex items-baseline justify-between gap-2">
                            <span className="text-foreground truncate text-sm">{item.label}</span>
                            {item.hint && <span className="text-muted-foreground shrink-0 text-xs">{item.hint}</span>}
                        </div>
                        <div className="bg-muted mt-1 h-2 overflow-hidden rounded-full">
                            <div
                                className="h-full rounded-full"
                                style={{ width: `${Math.max((item.value / max) * 100, item.value > 0 ? 2 : 0)}%`, background: color }}
                            />
                        </div>
                    </div>
                    <span className="text-foreground text-sm font-medium tabular-nums">{formatValue(item.value)}</span>
                </div>
            ))}
        </div>
    );
}
