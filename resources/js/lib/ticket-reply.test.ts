import { describe, expect, it } from 'vitest';
import { replyHint } from './ticket-reply';

describe('replyHint', () => {
    const client = { name: 'Dana', email: 'dana@client.test' };

    it('says a reply to a chat client goes by email, and to which address', () => {
        expect(replyHint(client, false)).toBe('Emailed to dana@client.test. Their answer by email comes back to you.');
    });

    it('says an internal note on a chat ticket is never emailed', () => {
        expect(replyHint(client, true)).toBe('Kept internal — never emailed to the client.');
    });

    it('keeps the portal wording for a signed-in requester', () => {
        expect(replyHint(null, false)).toBe('The requester will see this reply.');
        expect(replyHint(null, true)).toBe('Kept internal — the client portal never shows this.');
    });
});
