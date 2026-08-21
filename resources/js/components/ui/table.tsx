import { cn } from '@/lib/utils';

/**
 * The shared data table (audit §18 follow-through).
 *
 * Every CRM list hand-rolled the same `<div class="overflow-x-auto rounded-lg
 * border"><table>…` with slightly different padding and header styling. These
 * thin primitives centralise that markup so every table scrolls, rounds and
 * hovers the same way: an uppercase muted header, comfortable cell padding, and
 * a brand-aware row hover. Cells stay fully composable for per-column content.
 */

export function Table({
    className,
    containerClassName,
    ...props
}: React.TableHTMLAttributes<HTMLTableElement> & { containerClassName?: string }) {
    return (
        <div className={cn('border-border overflow-x-auto rounded-lg border', containerClassName)}>
            <table className={cn('w-full text-left text-sm', className)} {...props} />
        </div>
    );
}

export function TableHeader({ className, ...props }: React.HTMLAttributes<HTMLTableSectionElement>) {
    return <thead className={cn('bg-muted/50', className)} {...props} />;
}

export function TableBody({ className, ...props }: React.HTMLAttributes<HTMLTableSectionElement>) {
    return <tbody className={cn('divide-border divide-y', className)} {...props} />;
}

export function TableRow({ className, ...props }: React.HTMLAttributes<HTMLTableRowElement>) {
    return <tr className={cn('hover:bg-muted/40 transition-colors', className)} {...props} />;
}

export function TableHead({ className, ...props }: React.ThHTMLAttributes<HTMLTableCellElement>) {
    return <th className={cn('text-muted-foreground px-3 py-2.5 text-xs font-semibold tracking-wide uppercase', className)} {...props} />;
}

export function TableCell({ className, ...props }: React.TdHTMLAttributes<HTMLTableCellElement>) {
    return <td className={cn('px-3 py-2.5 align-middle', className)} {...props} />;
}
