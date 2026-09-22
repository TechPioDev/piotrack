import { describe, expect, it } from 'vitest';

import { pageMatches } from './targeting';

/**
 * Page rules decide which pages the chat appears on. A rule that matches too
 * much shows the chat where a customer said never; one that matches too little
 * hides it from the campaign it was set up for.
 */
describe('pageMatches', () => {
    it('matches a page and everything under it', () => {
        expect(pageMatches(['/cybersecurity'], '/cybersecurity')).toBe(true);
        expect(pageMatches(['/cybersecurity'], '/cybersecurity/mdr')).toBe(true);
        expect(pageMatches(['/cybersecurity'], '/cybersecurity-news')).toBe(false);
    });

    it('treats * as anything', () => {
        expect(pageMatches(['/services/*'], '/services/cloud')).toBe(true);
        expect(pageMatches(['/services/*'], '/about')).toBe(false);
    });

    it('matches a link setting on any page', () => {
        expect(pageMatches(['?utm_source=google'], '/pricing', '?utm_source=google&utm_medium=cpc')).toBe(true);
        expect(pageMatches(['?utm_source=google'], '/', '?utm_source=bing')).toBe(false);
        expect(pageMatches(['?utm_source=google'], '/', '')).toBe(false);
    });

    it('ignores case in values, since campaign tags are typed by hand', () => {
        expect(pageMatches(['?utm_campaign=spring'], '/', '?utm_campaign=Spring')).toBe(true);
    });

    it('needs both the page and the setting when a rule has both', () => {
        expect(pageMatches(['/pricing?ref=partner*'], '/pricing', '?ref=partner-acme')).toBe(true);
        expect(pageMatches(['/pricing?ref=partner*'], '/about', '?ref=partner-acme')).toBe(false);
        expect(pageMatches(['/pricing?ref=partner*'], '/pricing', '?ref=newsletter')).toBe(false);
    });

    it('needs every setting in a rule, and a bare name only needs it present', () => {
        expect(pageMatches(['?utm_source=google&utm_medium=cpc'], '/', '?utm_medium=cpc&utm_source=google')).toBe(true);
        expect(pageMatches(['?utm_source=google&utm_medium=cpc'], '/', '?utm_source=google')).toBe(false);
        expect(pageMatches(['?gclid'], '/', '?gclid=abc123')).toBe(true);
        expect(pageMatches(['?gclid'], '/', '?fbclid=abc123')).toBe(false);
    });

    it('ignores blank rules', () => {
        expect(pageMatches(['', '   '], '/')).toBe(false);
    });
});
