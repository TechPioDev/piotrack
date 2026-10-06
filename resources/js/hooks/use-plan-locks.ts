import { planLocks } from '@/lib/plan-areas';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

/**
 * Whether the workspace's plan leaves a page out - for marking menus only. The
 * backend `entitlement:` middleware is what refuses the page (ENTL-003).
 */
export function usePlanLocks(): (url: string) => boolean {
    const areas = usePage<SharedData>().props.entitlements?.areas;

    return useMemo(() => planLocks(areas), [areas]);
}
