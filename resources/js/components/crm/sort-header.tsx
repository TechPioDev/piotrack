import { TableHead } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';

/**
 * A sortable column header (CRMT module). First click sorts ascending, second
 * flips, and the arrow shows the active direction so the table state is never
 * a mystery. Columns the server does not allow-list simply don't use this.
 */
export function SortHeader({
    label,
    column,
    sort,
    dir,
    onSort,
    className,
}: {
    label: string;
    column: string;
    sort?: string;
    dir?: string;
    onSort: (column: string, dir: 'asc' | 'desc') => void;
    className?: string;
}) {
    const active = sort === column;
    const Icon = active ? (dir === 'desc' ? ArrowDown : ArrowUp) : ArrowUpDown;

    return (
        <TableHead className={className}>
            <button
                type="button"
                onClick={() => onSort(column, active && dir === 'asc' ? 'desc' : 'asc')}
                className={cn(
                    'hover:text-foreground -ml-1 flex items-center gap-1 rounded px-1 py-0.5 transition-colors',
                    active ? 'text-foreground font-semibold' : 'text-muted-foreground',
                )}
                aria-label={`Sort by ${label}${active ? (dir === 'asc' ? ', currently ascending' : ', currently descending') : ''}`}
            >
                {label}
                <Icon className={cn('size-3.5', !active && 'opacity-50')} aria-hidden />
            </button>
        </TableHead>
    );
}
