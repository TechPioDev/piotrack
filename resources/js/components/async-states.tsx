import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { AlertTriangle, Loader2, RefreshCw } from 'lucide-react';

/**
 * Async-state standard (DSGN-009): every screen that waits, fails, or half
 * fails says so in the same voice.
 *
 * - LoadingState while content is on its way (skeleton rows + a live label).
 * - ErrorState when it did not arrive, with a retry that actually retries.
 * - PartialFailure when the page rendered but a named part of it could not —
 *   the rest of the data stays visible instead of being thrown away.
 */

export function LoadingState({ label = 'Loading…', rows = 3 }: { label?: string; rows?: number }) {
    return (
        <div role="status" aria-live="polite" className="space-y-2">
            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                <Loader2 className="size-4 animate-spin" aria-hidden />
                {label}
            </div>
            {Array.from({ length: rows }, (_, i) => (
                <Skeleton key={i} className="h-8 w-full" />
            ))}
        </div>
    );
}

export function ErrorState({
    title = 'Something went wrong',
    message,
    onRetry,
    retryLabel = 'Try again',
}: {
    title?: string;
    message: string;
    onRetry?: () => void;
    retryLabel?: string;
}) {
    return (
        <Alert variant="destructive" role="alert">
            <AlertTriangle className="size-4" aria-hidden />
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription className="space-y-2">
                <p>{message}</p>
                {onRetry && (
                    <Button size="sm" variant="outline" onClick={onRetry}>
                        <RefreshCw className="mr-2 size-4" aria-hidden />
                        {retryLabel}
                    </Button>
                )}
            </AlertDescription>
        </Alert>
    );
}

export function PartialFailure({ failed, detail, onRetry }: { failed: string[]; detail?: string; onRetry?: () => void }) {
    if (failed.length === 0) {
        return null;
    }

    return (
        <Alert role="status">
            <AlertTriangle className="size-4" aria-hidden />
            <AlertTitle>Some of this page could not load</AlertTitle>
            <AlertDescription className="space-y-2">
                <p>
                    Unavailable right now: {failed.join(', ')}. Everything else on the page is current.
                    {detail ? ` ${detail}` : ''}
                </p>
                {onRetry && (
                    <Button size="sm" variant="outline" onClick={onRetry}>
                        <RefreshCw className="mr-2 size-4" aria-hidden />
                        Retry
                    </Button>
                )}
            </AlertDescription>
        </Alert>
    );
}
