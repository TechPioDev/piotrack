import { InitialAvatar } from '@/components/initial-avatar';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * The shared CRM presentation primitives: the polished data table that every
 * list adopts, and the brand initials chip used in tables and detail headers.
 */

describe('Table', () => {
    it('renders a scrollable, semantic table with headers and cells', () => {
        render(
            <Table>
                <TableHeader>
                    <tr>
                        <TableHead>Name</TableHead>
                        <TableHead>Email</TableHead>
                    </tr>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell>Ada Lovelace</TableCell>
                        <TableCell>ada@example.com</TableCell>
                    </TableRow>
                </TableBody>
            </Table>,
        );

        expect(screen.getByRole('columnheader', { name: 'Name' })).toBeInTheDocument();
        expect(screen.getByRole('cell', { name: 'Ada Lovelace' })).toBeInTheDocument();
        expect(screen.getByRole('cell', { name: 'ada@example.com' })).toBeInTheDocument();
    });
});

describe('InitialAvatar', () => {
    it('derives two initials from a full name', () => {
        render(<InitialAvatar name="Ada Lovelace" />);
        expect(screen.getByText('AL')).toBeInTheDocument();
    });

    it('uses a single initial for a one-word name', () => {
        render(<InitialAvatar name="Acme" />);
        expect(screen.getByText('A')).toBeInTheDocument();
    });

    it('falls back to a placeholder when the name is empty', () => {
        render(<InitialAvatar name="" />);
        expect(screen.getByText('?')).toBeInTheDocument();
    });
});
