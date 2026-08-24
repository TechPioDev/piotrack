import { describe, expect, it, vi } from 'vitest';

import { fetchWithRetry } from './retry';

/**
 * A failed request ends the conversation and tells the visitor chat is
 * unavailable, so what counts as "failed" matters. This pins the line: a blip
 * gets a second chance, a definite answer does not.
 */
const reply = (status: number) => new Response('{}', { status });

describe('fetchWithRetry', () => {
    it('returns the first response when it succeeds', async () => {
        const run = vi.fn().mockResolvedValue(reply(200));

        expect((await fetchWithRetry(run, 0)).status).toBe(200);
        expect(run).toHaveBeenCalledTimes(1);
    });

    it('tries again after a dropped connection', async () => {
        // fetch rejects when the request never reached the server at all.
        const run = vi.fn().mockRejectedValueOnce(new TypeError('Failed to fetch')).mockResolvedValue(reply(200));

        expect((await fetchWithRetry(run, 0)).status).toBe(200);
        expect(run).toHaveBeenCalledTimes(2);
    });

    it('tries again after a server error', async () => {
        // The exact case seen live: a transient 500 mid-conversation, which
        // previously ended the chat with "temporarily unavailable".
        const run = vi.fn().mockResolvedValueOnce(reply(500)).mockResolvedValue(reply(200));

        expect((await fetchWithRetry(run, 0)).status).toBe(200);
        expect(run).toHaveBeenCalledTimes(2);
    });

    it('does not retry a client error', async () => {
        // 422 validation, 410 closed, 404 gone, 429 throttled: asking again wins
        // nothing and makes the visitor wait for the same answer.
        for (const status of [404, 410, 422, 429]) {
            const run = vi.fn().mockResolvedValue(reply(status));

            expect((await fetchWithRetry(run, 0)).status).toBe(status);
            expect(run).toHaveBeenCalledTimes(1);
        }
    });

    it('gives up after one retry rather than hammering a failing server', async () => {
        const run = vi.fn().mockResolvedValue(reply(500));

        expect((await fetchWithRetry(run, 0)).status).toBe(500);
        expect(run).toHaveBeenCalledTimes(2);
    });

    it('surfaces the second failure when the connection stays down', async () => {
        const run = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));

        await expect(fetchWithRetry(run, 0)).rejects.toThrow('Failed to fetch');
        expect(run).toHaveBeenCalledTimes(2);
    });
});
