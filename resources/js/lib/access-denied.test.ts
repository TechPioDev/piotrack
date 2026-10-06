import { describe, expect, it } from 'vitest';
import { deniedCopy, type Denied } from './access-denied';

const plan = (over: Partial<Extract<Denied, { reason: 'plan' }>> = {}): Denied => ({
    reason: 'plan',
    workspace: 'PioManage',
    state: 'trial_ended',
    plan: 'Growth',
    ended_on: '2026-10-06',
    can_manage_billing: true,
    ...over,
});

describe('deniedCopy', () => {
    it('tells an owner their trial ended, and sends them to the plans', () => {
        const copy = deniedCopy(plan());

        expect(copy.title).toBe('Your free trial has ended');
        expect(copy.body).toContain('The free trial for PioManage ended on');
        expect(copy.body).toContain('2026');
        expect(copy.body).toContain('choose a plan to carry on');
        expect(copy.action).toEqual({ label: 'See plans', href: '/billing/plans' });
    });

    it('sends someone who cannot change the plan to an owner instead', () => {
        const copy = deniedCopy(plan({ can_manage_billing: false }));

        expect(copy.action).toBeNull();
        expect(copy.body).toContain('ask an owner of the workspace to choose a plan');
    });

    it('names the plan when it simply does not include the feature', () => {
        const copy = deniedCopy(plan({ state: 'not_included', ended_on: null }));

        expect(copy.title).toBe('Not included in your plan');
        expect(copy.body).toContain('The Growth plan does not include this part of Piotrack.');
    });

    it('says a held subscription is usually about a payment', () => {
        expect(deniedCopy(plan({ state: 'suspended' })).body).toContain('a payment did not go through');
    });

    it('keeps a role refusal about the role, with nothing to buy', () => {
        const copy = deniedCopy({ reason: 'role', workspace: 'PioManage', role: 'Viewer' });

        expect(copy.title).toBe('Not authorized');
        expect(copy.body).toBe(
            'Your role in PioManage (Viewer) does not include this page. If you need it, ask an owner of the workspace to change your role.',
        );
        expect(copy.action).toBeNull();
    });
});
