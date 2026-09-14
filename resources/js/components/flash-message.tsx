import { Alert, AlertDescription } from '@/components/ui/alert';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Renders the session flash so the server's confirmations (`status`) and
 * refusals (`error`) are actually visible. Each resets whenever a new message
 * of its kind arrives.
 */
export function FlashMessage({
    className = 'px-4 pt-4',
    kinds = ['error', 'status'],
}: {
    className?: string;
    /** Auth pages render `status` from their own props, so they ask for errors only. */
    kinds?: ('error' | 'status')[];
}) {
    const { flash } = usePage<SharedData>().props;
    const status = flash?.status ?? null;
    const error = flash?.error ?? null;
    const [dismissed, setDismissed] = useState<{ status: boolean; error: boolean }>({ status: false, error: false });

    useEffect(() => {
        setDismissed((current) => ({ ...current, status: false }));
    }, [status]);

    useEffect(() => {
        setDismissed((current) => ({ ...current, error: false }));
    }, [error]);

    const messages: { kind: 'error' | 'status'; text: string }[] = [];
    if (error && !dismissed.error && kinds.includes('error')) messages.push({ kind: 'error', text: error });
    if (status && !dismissed.status && kinds.includes('status')) messages.push({ kind: 'status', text: status });

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className={cn('space-y-2', className)}>
            {messages.map((message) => {
                const Icon = message.kind === 'error' ? AlertTriangle : CheckCircle2;

                return (
                    <Alert key={message.kind} variant={message.kind === 'error' ? 'destructive' : 'default'} className="flex items-start gap-2">
                        <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <AlertDescription className="flex-1">{message.text}</AlertDescription>
                        <button
                            type="button"
                            onClick={() => setDismissed((current) => ({ ...current, [message.kind]: true }))}
                            aria-label="Dismiss message"
                            className="text-muted-foreground hover:text-foreground shrink-0"
                        >
                            <X className="size-4" />
                        </button>
                    </Alert>
                );
            })}
        </div>
    );
}
