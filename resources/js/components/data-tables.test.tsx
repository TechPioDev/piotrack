import { SortHeader } from '@/components/crm/sort-header';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

/**
 * UI-P2 table standards: numeric sort headers align over their digits, and
 * every table's header row has exactly as many columns as its body row.
 */

function renderHeader(props: Partial<Parameters<typeof SortHeader>[0]> = {}) {
    const onSort = vi.fn();
    render(
        <table>
            <thead>
                <tr>
                    <SortHeader label="Deals" column="deals_count" onSort={onSort} {...props} />
                </tr>
            </thead>
        </table>,
    );

    return { onSort, header: screen.getByRole('columnheader'), button: screen.getByRole('button') };
}

describe('SortHeader', () => {
    it('keeps text columns left-aligned by default', () => {
        const { header, button } = renderHeader();

        expect(header.className).not.toContain('text-right');
        expect(button.className).toContain('-ml-1');
    });

    it('pushes a numeric column header to the right edge', () => {
        const { header, button } = renderHeader({ align: 'right' });

        expect(header.className).toContain('text-right');
        expect(button.className).toContain('ml-auto');
    });

    it('sorts an inactive column ascending first', async () => {
        const { onSort, button } = renderHeader();
        await userEvent.click(button);
        expect(onSort).toHaveBeenCalledWith('deals_count', 'asc');
    });

    it('flips an ascending column to descending', async () => {
        const { onSort, button } = renderHeader({ sort: 'deals_count', dir: 'asc' });
        await userEvent.click(button);
        expect(onSort).toHaveBeenCalledWith('deals_count', 'desc');
    });
});

const pages = import.meta.glob('../pages/**/*.tsx', { query: '?raw', import: 'default', eager: true }) as Record<string, string>;

describe('table structure across every page', () => {
    it('gives each table as many header cells as body cells', () => {
        const mismatches: string[] = [];

        for (const [file, source] of Object.entries(pages)) {
            for (const table of source.matchAll(/<(table|Table)\b[\s\S]*?<\/(table|Table)>/g)) {
                const head = table[0].match(/<(thead|TableHeader)\b[\s\S]*?<\/(thead|TableHeader)>/);
                const body = table[0].match(/<(tbody|TableBody)\b[\s\S]*?<\/(tbody|TableBody)>/);
                const row = body?.[0].match(/<(tr|TableRow)\b[\s\S]*?<\/(tr|TableRow)>/);
                if (!head || !row || row[0].includes('colSpan')) continue;

                const headers = head[0].match(/<(th|TableHead|SortHeader)\b/g)?.length ?? 0;
                const cells = row[0].match(/<(td|TableCell)\b/g)?.length ?? 0;
                if (headers !== cells) {
                    const line = source.slice(0, table.index).split('\n').length;
                    mismatches.push(`${file}:${line} has ${headers} headers but ${cells} cells`);
                }
            }
        }

        expect(Object.keys(pages).length).toBeGreaterThan(100);
        expect(mismatches).toEqual([]);
    });
});
