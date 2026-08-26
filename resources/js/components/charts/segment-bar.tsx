import { cn } from '@/lib/utils';

export type Segment = { label: string; value: number; color?: string };

const DEFAULT_COLORS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

/**
 * One stacked distribution bar with a legend (design-shell module): lead
 * temperature, ranking buckets, status mixes. Zero-value segments stay in
 * the legend (a zero is information) but take no bar space.
 */
export function SegmentBar({
    segments,
    formatValue = (v: number) => String(v),
    emptyText = 'No data yet.',
    ariaLabel,
    className,
}: {
    segments: Segment[];
    formatValue?: (value: number) => string;
    emptyText?: string;
    ariaLabel: string;
    className?: string;
}) {
    const total = segments.reduce((sum, s) => sum + s.value, 0);

    if (segments.length === 0 || total === 0) {
        return (
            <p className={cn('text-muted-foreground py-6 text-center text-sm', className)} role="img" aria-label={`${ariaLabel}: no data`}>
                {emptyText}
            </p>
        );
    }

    return (
        <div className={className} role="img" aria-label={ariaLabel}>
            <div className="bg-muted flex h-3 overflow-hidden rounded-full">
                {segments.map(
                    (segment, i) =>
                        segment.value > 0 && (
                            <div
                                key={segment.label}
                                style={{
                                    width: `${(segment.value / total) * 100}%`,
                                    background: segment.color ?? DEFAULT_COLORS[i % DEFAULT_COLORS.length],
                                }}
                            />
                        ),
                )}
            </div>
            <div className="mt-2.5 flex flex-wrap gap-x-4 gap-y-1.5">
                {segments.map((segment, i) => (
                    <span key={segment.label} className="flex items-center gap-1.5 text-xs">
                        <span className="size-2.5 rounded-full" style={{ background: segment.color ?? DEFAULT_COLORS[i % DEFAULT_COLORS.length] }} />
                        <span className="text-muted-foreground">{segment.label}</span>
                        <span className="text-foreground font-medium tabular-nums">{formatValue(segment.value)}</span>
                    </span>
                ))}
            </div>
        </div>
    );
}
