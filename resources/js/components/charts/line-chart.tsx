import { cn } from '@/lib/utils';

export type LinePoint = { label: string; value: number };

/**
 * Trend line for dashboards (design-shell module). Hand-rolled SVG on the
 * --chart-* tokens: no chart dependency, themes for free, and nothing to
 * render but the data — an empty series shows an honest empty state instead
 * of an invented flat line.
 */
export function LineChart({
    data,
    height = 160,
    color = 'var(--chart-1)',
    formatValue = (v: number) => String(v),
    emptyText = 'No data for this period yet.',
    ariaLabel,
    className,
}: {
    data: LinePoint[];
    height?: number;
    color?: string;
    formatValue?: (value: number) => string;
    emptyText?: string;
    ariaLabel: string;
    className?: string;
}) {
    if (data.length === 0 || data.every((p) => p.value === 0)) {
        return (
            <div
                className={cn('text-muted-foreground flex items-center justify-center text-sm', className)}
                style={{ height }}
                role="img"
                aria-label={`${ariaLabel}: no data`}
            >
                {emptyText}
            </div>
        );
    }

    const width = 600;
    const pad = { top: 14, right: 14, bottom: 22, left: 14 };
    const innerW = width - pad.left - pad.right;
    const innerH = height - pad.top - pad.bottom;

    const max = Math.max(...data.map((p) => p.value));
    const min = Math.min(...data.map((p) => p.value), 0);
    const range = max - min || 1;
    const x = (i: number) => pad.left + (data.length === 1 ? innerW / 2 : (i / (data.length - 1)) * innerW);
    const y = (v: number) => pad.top + innerH - ((v - min) / range) * innerH;

    const line = data.map((p, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(p.value).toFixed(1)}`).join(' ');
    const area = `${line} L${x(data.length - 1).toFixed(1)},${(pad.top + innerH).toFixed(1)} L${x(0).toFixed(1)},${(pad.top + innerH).toFixed(1)} Z`;
    const last = data[data.length - 1];

    return (
        <div className={className}>
            <svg viewBox={`0 0 ${width} ${height}`} className="w-full" style={{ height }} role="img" aria-label={ariaLabel}>
                {/* faint guides at max and baseline keep the scale readable */}
                <line x1={pad.left} x2={width - pad.right} y1={y(max)} y2={y(max)} stroke="var(--border)" strokeDasharray="3 4" />
                <line x1={pad.left} x2={width - pad.right} y1={pad.top + innerH} y2={pad.top + innerH} stroke="var(--border)" />
                <path d={area} fill={color} opacity={0.12} />
                <path d={line} fill="none" stroke={color} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
                <circle cx={x(data.length - 1)} cy={y(last.value)} r={4} fill={color} />
                <text x={pad.left} y={y(max) - 4} className="fill-muted-foreground" fontSize={10}>
                    {formatValue(max)}
                </text>
                <text x={pad.left} y={height - 6} className="fill-muted-foreground" fontSize={10}>
                    {data[0].label}
                </text>
                <text x={width - pad.right} y={height - 6} textAnchor="end" className="fill-muted-foreground" fontSize={10}>
                    {last.label}
                </text>
            </svg>
        </div>
    );
}
