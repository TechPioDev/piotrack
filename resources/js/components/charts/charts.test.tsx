import { BarList } from '@/components/charts/bar-list';
import { LineChart } from '@/components/charts/line-chart';
import { SegmentBar } from '@/components/charts/segment-bar';
import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * The chart kit's contract (design-shell module): real data renders geometry,
 * no data renders an honest empty state, and degenerate series (one point,
 * all zeros) never produce NaN coordinates.
 */

describe('LineChart', () => {
    it('renders a path through the data and marks the last point', () => {
        const { container } = render(
            <LineChart
                ariaLabel="Leads trend"
                data={[
                    { label: 'Aug 1', value: 2 },
                    { label: 'Aug 2', value: 5 },
                    { label: 'Aug 3', value: 3 },
                ]}
            />,
        );

        const paths = container.querySelectorAll('path');
        expect(paths.length).toBe(2); // area + line
        expect(container.querySelector('circle')).not.toBeNull();
        expect(container.innerHTML).not.toContain('NaN');
        expect(container.textContent).toContain('Aug 1');
        expect(container.textContent).toContain('Aug 3');
    });

    it('shows an empty state instead of inventing a flat line', () => {
        const { container: empty } = render(<LineChart ariaLabel="Leads trend" data={[]} />);
        expect(empty.querySelector('svg')).toBeNull();
        expect(empty.textContent).toContain('No data');

        const { container: zeros } = render(
            <LineChart
                ariaLabel="Leads trend"
                data={[
                    { label: 'A', value: 0 },
                    { label: 'B', value: 0 },
                ]}
            />,
        );
        expect(zeros.querySelector('svg')).toBeNull();
    });

    it('survives a single-point series without NaN', () => {
        const { container } = render(<LineChart ariaLabel="Trend" data={[{ label: 'Aug 1', value: 7 }]} />);
        expect(container.innerHTML).not.toContain('NaN');
        expect(container.querySelector('circle')).not.toBeNull();
    });
});

describe('BarList', () => {
    it('scales bars to the largest value and formats numbers', () => {
        const { container, getByText } = render(
            <BarList
                ariaLabel="Channel comparison"
                formatValue={(v) => `$${v}`}
                items={[
                    { label: 'Organic', value: 100 },
                    { label: 'Paid', value: 50 },
                ]}
            />,
        );

        const bars = [...container.querySelectorAll('div[style]')].map((el) => (el as HTMLElement).style.width);
        expect(bars).toContain('100%');
        expect(bars).toContain('50%');
        expect(getByText('$100')).toBeTruthy();
    });

    it('shows an empty state when there is nothing to compare', () => {
        const { container } = render(<BarList ariaLabel="Channels" items={[]} />);
        expect(container.textContent).toContain('Nothing to compare yet');
    });
});

describe('SegmentBar', () => {
    it('splits the bar by share and keeps zero segments in the legend only', () => {
        const { container, getByText } = render(
            <SegmentBar
                ariaLabel="Lead temperature"
                segments={[
                    { label: 'Hot', value: 3 },
                    { label: 'Warm', value: 1 },
                    { label: 'Cold', value: 0 },
                ]}
            />,
        );

        const widths = [...container.querySelectorAll('div[style]')].map((el) => (el as HTMLElement).style.width).filter(Boolean);
        expect(widths).toContain('75%');
        expect(widths).toContain('25%');
        expect(getByText('Cold')).toBeTruthy(); // zero stays visible as information
    });

    it('shows an empty state at zero total', () => {
        const { container } = render(<SegmentBar ariaLabel="Mix" segments={[{ label: 'A', value: 0 }]} />);
        expect(container.textContent).toContain('No data yet');
    });
});
