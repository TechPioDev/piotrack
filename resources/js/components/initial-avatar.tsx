import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

/**
 * A small brand-tinted initials chip.
 *
 * Gives contacts, companies and people a visual anchor in tables and detail
 * headers without pulling in real avatar images. Decorative, so it is hidden
 * from assistive tech - the adjacent name is the accessible label.
 */
export function InitialAvatar({ name, className }: { name: string; className?: string }) {
    const getInitials = useInitials();
    const initials = getInitials(name) || '?';

    return (
        <span
            className={cn(
                'bg-brand-soft text-brand-strong flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold uppercase select-none',
                className,
            )}
            aria-hidden
        >
            {initials}
        </span>
    );
}
