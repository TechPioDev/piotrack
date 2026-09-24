/** Someone outside the workspace who asked for help, such as a client on the website chat. */
export type OutsideRequester = { name: string | null; email: string };

/**
 * Where a reply on a ticket goes, said plainly. A client who asked on the
 * website chat has no portal account: a reply reaches them by email, or not at
 * all - and getting that wrong sends internal commentary to a customer.
 */
export function replyHint(requester: OutsideRequester | null, internal: boolean): string {
    if (internal) {
        return requester ? 'Kept internal — never emailed to the client.' : 'Kept internal — the client portal never shows this.';
    }

    return requester ? `Emailed to ${requester.email}. Their answer by email comes back to you.` : 'The requester will see this reply.';
}
