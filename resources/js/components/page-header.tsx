import { cn } from '@/lib/utils';

/**
 * The canonical page header (audit §14).
 *
 * Every authenticated page opens with one title, an optional one-line
 * description, and its primary actions on the right - replacing the ad-hoc
 * `<div className="flex"><Heading/><buttons/></div>` that 68 pages each wrote
 * their own way. An optional row below (tabs, filters, a search field) keeps the
 * page's controls attached to its header rather than floating loose.
 *
 * Typography follows the system: a 24px semibold title, a small muted
 * description. No 50px marketing headings inside the product.
 */
export function PageHeader({
    title,
    description,
    actions,
    children,
    className,
}: {
    title: string;
    description?: string;
    actions?: React.ReactNode;
    children?: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-col gap-4', className)}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h1 className="text-foreground text-2xl font-semibold tracking-tight text-balance">{title}</h1>
                    {description && <p className="text-muted-foreground max-w-2xl text-sm">{description}</p>}
                </div>
                {actions && <div className="flex max-w-full shrink-0 flex-wrap items-center gap-2">{actions}</div>}
            </div>
            {children}
        </div>
    );
}
