import { usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';

import type { SharedData } from '@/types';

/**
 * Explains why a signed-in user is suddenly being asked to set up 2FA.
 *
 * Enforcement redirects to the setup page, which sits behind password
 * confirmation — so the first thing someone sees is a password prompt with no
 * stated reason. A flash message cannot carry that far: it is consumed by the
 * intermediate redirect. This reads the enforcement state directly instead, so
 * it shows on every page in the chain.
 */
export function TwoFactorRequiredNotice() {
    const { twoFactorRequired } = usePage<SharedData>().props;

    if (!twoFactorRequired) {
        return null;
    }

    return (
        <div role="status" className="mb-6 flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm">
            <ShieldAlert className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-500" />
            <div className="space-y-1">
                <p className="font-medium">Two-factor authentication is now required</p>
                <p className="text-muted-foreground">
                    Set it up to continue. You will need an authenticator app such as Google Authenticator, 1Password or Authy.
                </p>
            </div>
        </div>
    );
}
