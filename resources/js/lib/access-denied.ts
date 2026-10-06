/** Why the server refused a page (403): the workspace's plan, or the person's role. */
export type Denied =
    | {
          reason: 'plan';
          workspace: string;
          state: 'trial_ended' | 'ended' | 'suspended' | 'none' | 'not_included';
          plan: string | null;
          ended_on: string | null;
          can_manage_billing: boolean;
      }
    | { reason: 'role'; workspace: string; role: string | null };

export type DeniedCopy = { title: string; body: string; action: { label: string; href: string } | null };

const day = (iso: string | null) =>
    iso === null ? null : new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' });

/**
 * What to tell someone the server turned away. A plan and a role are fixed by
 * different people in different places, so each gets its own words and - where
 * the reader can do something about it - the way there.
 */
export function deniedCopy(denied: Denied): DeniedCopy {
    if (denied.reason === 'role') {
        const role = denied.role ? ` (${denied.role})` : '';

        return {
            title: 'Not authorized',
            body: `Your role in ${denied.workspace}${role} does not include this page. If you need it, ask an owner of the workspace to change your role.`,
            action: null,
        };
    }

    const on = day(denied.ended_on);
    const [title, why] = ((): [string, string] => {
        switch (denied.state) {
            case 'trial_ended':
                return [
                    'Your free trial has ended',
                    `The free trial for ${denied.workspace} ended${on ? ` on ${on}` : ''}, so this part of Piotrack is switched off.`,
                ];
            case 'ended':
                return [
                    'Your subscription has ended',
                    `The subscription for ${denied.workspace} ended${on ? ` on ${on}` : ''}, so this part of Piotrack is switched off.`,
                ];
            case 'suspended':
                return [
                    'Your subscription is on hold',
                    `The subscription for ${denied.workspace} is on hold, usually because a payment did not go through.`,
                ];
            case 'none':
                return ['No plan yet', `${denied.workspace} does not have a plan yet, so this part of Piotrack is switched off.`];
            default:
                return [
                    'Not included in your plan',
                    `${denied.plan ? `The ${denied.plan} plan` : 'Your plan'} does not include this part of Piotrack.`,
                ];
        }
    })();

    return denied.can_manage_billing
        ? { title, body: `${why} Nothing has been deleted — choose a plan to carry on.`, action: { label: 'See plans', href: '/billing/plans' } }
        : { title, body: `${why} Nothing has been deleted — ask an owner of the workspace to choose a plan.`, action: null };
}
