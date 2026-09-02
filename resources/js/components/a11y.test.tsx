import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { describe, expect, it } from 'vitest';

import { ErrorState, LoadingState, PartialFailure } from './async-states';
import { ConfirmAction } from './confirm-action';
import InputError from './input-error';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from './ui/table';

/**
 * DSGN-004: automated WCAG checks (axe-core) on the composites every screen
 * is built from. jsdom cannot paint, so the color-contrast rule is exercised
 * separately against the live app (docs/design-system.md records the result);
 * everything else — names, roles, labels, ARIA validity, structure — runs
 * here on every test run.
 */
async function expectNoViolations(container: Element) {
    const result = await axe.run(container, {
        rules: {
            'color-contrast': { enabled: false }, // needs real rendering
            region: { enabled: false }, // fragments render without landmarks
        },
    });

    expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(' | ')}`)).toEqual([]);
}

describe('accessibility of core composites', () => {
    it('labeled form field with an error message', async () => {
        const { container } = render(
            <form>
                <Label htmlFor="kw">Keyword</Label>
                <Input id="kw" defaultValue="managed it services" />
                <InputError message="Already tracked." />
                <Button type="submit">Save</Button>
            </form>,
        );

        await expectNoViolations(container);
    });

    it('data table', async () => {
        const { container } = render(
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Keyword</TableHead>
                        <TableHead>Position</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell>managed it services</TableCell>
                        <TableCell>4</TableCell>
                    </TableRow>
                </TableBody>
            </Table>,
        );

        await expectNoViolations(container);
    });

    it('confirmation dialog in its open state', async () => {
        render(
            <ConfirmAction title="Delete this?" description="It cannot be undone." onConfirm={() => {}}>
                <Button>Delete</Button>
            </ConfirmAction>,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Delete' }));

        await expectNoViolations(screen.getByRole('dialog'));
    });

    it('async states', async () => {
        const { container } = render(
            <div>
                <LoadingState label="Loading…" />
                <ErrorState message="It broke." onRetry={() => {}} />
                <PartialFailure failed={['Live AI engine data']} />
            </div>,
        );

        await expectNoViolations(container);
    });
});
