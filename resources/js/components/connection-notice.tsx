import { router } from '@inertiajs/react';
import { WifiOff, X } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Says so when a visit or submit never reached the server (dropped Wi-Fi, a
 * VPN reconnect, a deploy restarting the app). Inertia signals that only
 * through its `exception` event — deliberately cancelled visits don't fire it —
 * and without this the click simply did nothing. Server errors with a response
 * are not this: they render the error page.
 */
export function ConnectionNotice() {
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const stopException = router.on('exception', (event) => {
            event.preventDefault();
            setFailed(true);
        });
        const stopSuccess = router.on('success', () => setFailed(false));

        return () => {
            stopException();
            stopSuccess();
        };
    }, []);

    if (!failed) {
        return null;
    }

    return (
        <div
            role="alert"
            className="border-destructive/40 bg-background text-foreground fixed bottom-4 left-1/2 z-50 flex w-[min(28rem,calc(100vw-2rem))] -translate-x-1/2 items-start gap-3 rounded-lg border p-4 shadow-lg"
        >
            <WifiOff className="text-destructive mt-0.5 size-4 shrink-0" aria-hidden />
            <div className="flex-1 text-sm">
                <p className="font-medium">Couldn&apos;t reach Piotrack</p>
                <p className="text-muted-foreground">Nothing was saved or loaded. Check your connection, then try again.</p>
            </div>
            <button
                type="button"
                onClick={() => setFailed(false)}
                aria-label="Dismiss"
                className="text-muted-foreground hover:text-foreground shrink-0"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}
