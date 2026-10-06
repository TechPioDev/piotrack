import { planNoticeCopy } from '@/lib/access-denied';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CircleAlert, X } from 'lucide-react';
import { useState } from 'react';

const DISMISSED_KEY = 'piotrack.plan-notice.dismissed';

/** Reading or writing can throw in a private window; the notice simply stays. */
function dismissedFor(): string | null {
    try {
        return window.sessionStorage.getItem(DISMISSED_KEY);
    } catch {
        return null;
    }
}

/**
 * Says, on every page, that the workspace has no plan running (ENTL-009).
 *
 * When a trial ran out or a subscription ended, most of the product switched
 * off at once and the first anyone knew of it was a refused page. This is the
 * same news told beforehand: what happened, when, that nothing was deleted,
 * and - for someone who can change the plan - the way to do it.
 *
 * It can be put away for the rest of the visit, since somebody who cannot
 * change the plan has no use for a line they cannot act on. It comes back in
 * a new session, and at once if the plan's situation changes.
 */
export function PlanNotice() {
    const { planNotice } = usePage<SharedData>().props;
    const path = usePage().url.split('?')[0];
    const signature = planNotice ? `${planNotice.workspace}|${planNotice.state}|${planNotice.ended_on ?? ''}` : '';
    const [dismissed, setDismissed] = useState<string | null>(dismissedFor);

    if (!planNotice || dismissed === signature) {
        return null;
    }

    const { text, action } = planNoticeCopy(planNotice);

    const dismiss = () => {
        setDismissed(signature);
        try {
            window.sessionStorage.setItem(DISMISSED_KEY, signature);
        } catch {
            /* put away for this page only */
        }
    };

    return (
        <div role="status" className="border-b border-amber-500/40 bg-amber-500/10">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-2.5">
                <CircleAlert className="size-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                <p className="text-foreground min-w-0 flex-1 basis-64 text-sm">{text}</p>
                {/* No point sending someone to the page they are already on. */}
                {action && path !== action.href && (
                    <Link
                        href={action.href}
                        className="bg-foreground text-background shrink-0 rounded-md px-3 py-1.5 text-sm font-semibold transition-opacity hover:opacity-90"
                    >
                        {action.label}
                    </Link>
                )}
                <button
                    type="button"
                    onClick={dismiss}
                    aria-label="Hide this notice for now"
                    className="text-muted-foreground hover:text-foreground focus-visible:ring-ring ml-auto shrink-0 rounded p-1 focus-visible:ring-2 focus-visible:outline-hidden"
                >
                    <X className="size-4" aria-hidden="true" />
                </button>
            </div>
        </div>
    );
}
