import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { ConfirmAction } from './confirm-action';
import { Button } from './ui/button';

/**
 * DSGN-008: nothing destructive fires from one click. The trigger only opens
 * a dialog; the action runs on the explicit confirm and never on cancel.
 */
describe('ConfirmAction', () => {
    function setup(onConfirm = vi.fn()) {
        render(
            <ConfirmAction title="Delete this keyword?" description="Rank history goes with it." confirmLabel="Delete" onConfirm={onConfirm}>
                <Button>Delete</Button>
            </ConfirmAction>,
        );
        return onConfirm;
    }

    it('does not run the action from the trigger click alone', async () => {
        const onConfirm = setup();

        await userEvent.click(screen.getByRole('button', { name: 'Delete' }));

        expect(onConfirm).not.toHaveBeenCalled();
        expect(screen.getByRole('dialog')).toHaveTextContent('Delete this keyword?');
        expect(screen.getByRole('dialog')).toHaveTextContent('Rank history goes with it.');
    });

    it('runs the action exactly once on explicit confirm and closes', async () => {
        const onConfirm = setup();

        await userEvent.click(screen.getByRole('button', { name: 'Delete' }));
        await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Delete' }));

        expect(onConfirm).toHaveBeenCalledTimes(1);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('cancel closes without running anything', async () => {
        const onConfirm = setup();

        await userEvent.click(screen.getByRole('button', { name: 'Delete' }));
        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(onConfirm).not.toHaveBeenCalled();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
});
