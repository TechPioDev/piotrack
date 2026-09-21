import { Alert, AlertDescription } from '@/components/ui/alert';
import { cn } from '@/lib/utils';
import { AlertTriangle } from 'lucide-react';

/**
 * What the server refused, said once at the top of a form.
 *
 * Long settings forms put the failing field far below the save button, and a
 * field whose error is never rendered leaves the save looking simply ignored —
 * the chat widget's appearance settings failed exactly that way. This states the
 * refusal where the person just clicked, and names each field that caused it.
 */
export function FormErrors({ errors, className }: { errors: Record<string, string | undefined>; className?: string }) {
    const messages = Object.entries(errors)
        .filter(([, message]) => Boolean(message))
        .map(([field, message]) => ({ field, message: message as string }));

    if (messages.length === 0) {
        return null;
    }

    return (
        <Alert variant="destructive" className={cn('flex items-start gap-2', className)}>
            <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
            <AlertDescription className="flex-1">
                <p className="font-medium">
                    {messages.length === 1 ? 'This change was not saved.' : `${messages.length} fields kept this from saving.`}
                </p>
                <ul className={cn('mt-1 space-y-0.5', messages.length === 1 && 'mt-0.5')}>
                    {messages.map(({ field, message }) => (
                        <li key={field}>{message}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
