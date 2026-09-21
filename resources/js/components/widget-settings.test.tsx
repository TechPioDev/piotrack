import { FormErrors } from '@/components/form-errors';
import { widgetPayload } from '@/lib/chat-widget';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * Regression: the chat widget's appearance settings never saved. Two
 * transform() calls on one form (the second replacing the first) sent the page
 * rules as text, the server refused the save, and the page showed no error.
 */

describe('widgetPayload', () => {
    const form = {
        name: 'Chatbot',
        theme: { title: 'Chat with us', company: 'Umarketingit', accent: '#294294', position: 'bottom-left' },
        settings: {
            teaser: 'Hi Welcome to U marketing and IT',
            teaser_delay: 0,
            suggested_questions: 'Do you support Microsoft 365?\n\n  Pricing?  ',
        },
        targeting: { include: '/cybersecurity\n/services/*', exclude: ' /careers \n\n/privacy', visitor: 'all', delay_seconds: 1 },
        allowed_domains: 'umarketingit.com\n',
    };

    it('turns every list the page holds as text into an array in one pass', () => {
        const payload = widgetPayload(form);

        expect(payload.targeting).toMatchObject({ include: ['/cybersecurity', '/services/*'], exclude: ['/careers', '/privacy'] });
        expect(payload.allowed_domains).toEqual(['umarketingit.com']);
        expect(payload.settings).toMatchObject({ suggested_questions: ['Do you support Microsoft 365?', 'Pricing?'] });
    });

    it('leaves the appearance and every other value exactly as entered', () => {
        const payload = widgetPayload(form);

        expect(payload.theme).toEqual(form.theme);
        expect(payload.settings).toMatchObject({ teaser: 'Hi Welcome to U marketing and IT', teaser_delay: 0 });
        expect(payload.targeting).toMatchObject({ visitor: 'all', delay_seconds: 1 });
    });

    it('sends empty lists as empty arrays, which the server accepts', () => {
        const payload = widgetPayload({ ...form, allowed_domains: '', targeting: { include: '', exclude: '' } });

        expect(payload.allowed_domains).toEqual([]);
        expect(payload.targeting).toEqual({ include: [], exclude: [] });
    });

    it('keeps at most six suggested questions, as the server requires', () => {
        const payload = widgetPayload({ ...form, settings: { suggested_questions: 'a\nb\nc\nd\ne\nf\ng\nh' } });

        expect(payload.settings).toMatchObject({ suggested_questions: ['a', 'b', 'c', 'd', 'e', 'f'] });
    });
});

describe('FormErrors', () => {
    it('stays out of the way when nothing was refused', () => {
        const { container } = render(<FormErrors errors={{}} />);

        expect(container).toBeEmptyDOMElement();
    });

    it('says the change was not saved, and why', () => {
        render(<FormErrors errors={{ 'theme.accent': 'The accent colour must be a hex colour like #0bb39e.' }} />);

        expect(screen.getByRole('alert')).toHaveTextContent('This change was not saved.');
        expect(screen.getByRole('alert')).toHaveTextContent('The accent colour must be a hex colour like #0bb39e.');
    });

    it('counts the fields when several were refused', () => {
        render(<FormErrors errors={{ 'targeting.include': 'Include is wrong.', 'targeting.exclude': 'Exclude is wrong.', empty: undefined }} />);

        expect(screen.getByRole('alert')).toHaveTextContent('2 fields kept this from saving.');
        expect(screen.getAllByRole('listitem')).toHaveLength(2);
    });
});

describe('form transforms', () => {
    it('are set once per submit — a second transform() silently replaces the first', () => {
        const sources = import.meta.glob('../pages/**/*.tsx', { query: '?raw', import: 'default', eager: true }) as Record<string, string>;
        const doubled: string[] = [];

        for (const [file, source] of Object.entries(sources)) {
            const seen = new Map<string, number>();
            for (const match of source.matchAll(/\b(\w+)\.transform\(/g)) {
                const line = source.slice(0, match.index).split('\n').length;
                const previous = seen.get(match[1]);
                // Separate forms in one file sit far apart; two calls within a
                // few lines are the same submit handler overwriting itself.
                if (previous !== undefined && line - previous < 40) doubled.push(`${file}:${previous} and ${line}`);
                seen.set(match[1], line);
            }
        }

        expect(doubled).toEqual([]);
    });
});
