import { StatCard } from '@/components/stat-card';
import { countOf, formatDelta, shareOf } from '@/lib/format';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * UI-P3 tile context: a KPI tile says what its number is compared with, and
 * the helpers that build that context never invent a trend.
 */

describe('formatDelta', () => {
    it('signs a real change against the previous window', () => {
        expect(formatDelta({ value: 12, previous: 10, delta_pct: 20 })).toEqual({ value: '+20%', direction: 'up' });
        expect(formatDelta({ value: 5, previous: 10, delta_pct: -50 })).toEqual({ value: '-50%', direction: 'down' });
        expect(formatDelta({ value: 10, previous: 10, delta_pct: 0 })).toEqual({ value: '0%', direction: 'neutral' });
    });

    it('calls activity with no previous window "new" instead of an infinite rise', () => {
        expect(formatDelta({ value: 3, previous: 0, delta_pct: null })).toEqual({ value: 'new', direction: 'neutral' });
    });

    it('shows nothing when there was no activity in either window', () => {
        expect(formatDelta({ value: 0, previous: 0, delta_pct: null })).toBeUndefined();
    });
});

describe('context helpers', () => {
    it('pluralizes counts with grouping', () => {
        expect(countOf(1, 'form')).toBe('1 form');
        expect(countOf(1200, 'member')).toBe('1,200 members');
        expect(countOf(0, 'geo keyword')).toBe('0 geo keywords');
    });

    it('refuses a share when there is nothing to divide by', () => {
        expect(shareOf(11, 19)).toBe('58%');
        expect(shareOf(0, 0)).toBeNull();
    });
});

describe('StatCard', () => {
    it('renders the value, its delta and the context line', () => {
        render(<StatCard label="New contacts" value="19" delta={{ value: '+20%', direction: 'up' }} hint="42 total" />);

        expect(screen.getByText('New contacts')).toBeInTheDocument();
        expect(screen.getByText('19')).toBeInTheDocument();
        expect(screen.getByText('+20%')).toBeInTheDocument();
        expect(screen.getByText('42 total')).toBeInTheDocument();
    });

    it('omits the context line when there is none', () => {
        const { container } = render(<StatCard label="SQLs" value="4" />);

        expect(container.querySelector('p')).toBeNull();
    });
});
