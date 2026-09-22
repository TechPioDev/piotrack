import type { FlowNode } from './flow-tree';

/**
 * The block library: every step an owner can drag into a conversation, ready
 * to use. Contact blocks already save to the CRM fields the chat's capture
 * reads, so nobody types "first_name" - and Email and First name start out
 * required, the rest optional, because those two are what a lead needs.
 */

export type BlockGroup = 'Say' | 'Ask' | 'Contact details' | 'Actions' | 'Finish';

export type Block = {
    key: string;
    group: BlockGroup;
    label: string;
    hint: string;
    make: () => FlowNode;
};

export const BLOCK_GROUPS: BlockGroup[] = ['Say', 'Ask', 'Contact details', 'Actions', 'Finish'];

/** The contact fields the capture service copies onto the CRM contact and lead. */
export const CONTACT_FIELDS: Record<string, string> = {
    first_name: 'First name',
    last_name: 'Last name',
    email: 'Email',
    phone: 'Phone',
    company_name: 'Company',
};

const contact = (field: string, input: string, text: string, required: boolean): FlowNode => ({
    type: 'input',
    input,
    field,
    text,
    optional: !required,
});

export const BLOCKS: Block[] = [
    {
        key: 'message',
        group: 'Say',
        label: 'Message',
        hint: 'Say something, then carry on',
        make: () => ({ type: 'message', text: 'Thanks for stopping by!' }),
    },

    {
        key: 'question',
        group: 'Ask',
        label: 'Question with buttons',
        hint: 'Visitors tap an answer; each answer can lead its own way',
        make: () => ({
            type: 'choice',
            text: 'What can we help you with?',
            options: [
                { id: 'answer_1', label: 'First answer', score: 0 },
                { id: 'answer_2', label: 'Second answer', score: 0 },
            ],
        }),
    },
    {
        key: 'open',
        group: 'Ask',
        label: 'Open question',
        hint: 'Visitors type their own answer',
        make: () => ({ type: 'input', input: 'text', text: 'Tell us a little about what you need.', optional: false }),
    },
    {
        key: 'number',
        group: 'Ask',
        label: 'Number',
        hint: 'A number, such as team size',
        make: () => ({ type: 'input', input: 'number', text: 'How many people work at your company?', optional: false }),
    },

    {
        key: 'first_name',
        group: 'Contact details',
        label: 'First name',
        hint: 'Saved to the lead',
        make: () => contact('first_name', 'text', 'What is your first name?', true),
    },
    {
        key: 'last_name',
        group: 'Contact details',
        label: 'Last name',
        hint: 'Saved to the lead',
        make: () => contact('last_name', 'text', 'And your last name?', false),
    },
    {
        key: 'email',
        group: 'Contact details',
        label: 'Email',
        hint: 'Checked as a real address',
        make: () => contact('email', 'email', 'What is the best email to reach you?', true),
    },
    {
        key: 'phone',
        group: 'Contact details',
        label: 'Phone',
        hint: 'Checked as a phone number',
        make: () => contact('phone', 'phone', 'What is the best number to reach you?', false),
    },
    {
        key: 'company',
        group: 'Contact details',
        label: 'Company',
        hint: 'Saved to the lead',
        make: () => contact('company_name', 'company', 'What company are you with?', false),
    },

    {
        key: 'booking',
        group: 'Actions',
        label: 'Book a meeting',
        hint: 'Offers your free times as buttons',
        make: () => ({ type: 'booking', text: 'Pick a time that suits you:' }),
    },
    {
        key: 'handoff',
        group: 'Actions',
        label: 'Talk to a person',
        hint: 'Connects someone from your team if available',
        make: () => ({ type: 'handoff' }),
    },
    {
        key: 'ai',
        group: 'Actions',
        label: 'AI answers',
        hint: 'Answers typed questions about your business',
        make: () => ({ type: 'ai', text: 'What would you like to know?' }),
    },
    {
        key: 'score',
        group: 'Actions',
        label: 'Add to lead score',
        hint: 'Mark this path as a stronger lead',
        make: () => ({ type: 'score', points: 10 }),
    },
    { key: 'tag', group: 'Actions', label: 'Tag', hint: 'Label the conversation', make: () => ({ type: 'tag', tag: 'interested' }) },
    {
        key: 'assign',
        group: 'Actions',
        label: 'Send to a salesperson',
        hint: 'Choose who follows up',
        make: () => ({ type: 'assign', assignee_id: null }),
    },
    {
        key: 'condition',
        group: 'Actions',
        label: 'If an earlier answer…',
        hint: 'Go one way or another based on an answer',
        make: () => ({ type: 'condition', field: '', operator: 'equals', value: '' }),
    },

    {
        key: 'end_lead',
        group: 'Finish',
        label: 'Finish: new lead',
        hint: 'Saves them as a lead',
        make: () => ({ type: 'end', outcome: 'lead', text: 'Thanks! We will be in touch shortly.' }),
    },
    {
        key: 'end_meeting',
        group: 'Finish',
        label: 'Finish: offer a meeting',
        hint: 'Saves the lead and offers your booking page',
        make: () => ({ type: 'end', outcome: 'meeting', text: 'Great, pick a time that suits you.' }),
    },
    {
        key: 'end_support',
        group: 'Finish',
        label: 'Finish: support ticket',
        hint: 'For existing customers: opens a ticket, never a lead',
        make: () => ({ type: 'end', outcome: 'support', text: 'Thanks, we have opened a support ticket and will reply by email.' }),
    },
];

export function blockByKey(key: string): Block | undefined {
    return BLOCKS.find((b) => b.key === key);
}

/** The kind of step, in words - "Email", "Question", "Book a meeting" - for its card. */
export function stepKind(node: FlowNode): string {
    switch (node.type) {
        case 'message':
            return 'Message';
        case 'choice':
            return 'Question';
        case 'input':
            if (node.field && CONTACT_FIELDS[node.field]) return CONTACT_FIELDS[node.field];
            return node.input === 'number' ? 'Number' : node.input === 'email' ? 'Email' : node.input === 'phone' ? 'Phone' : 'Open question';
        case 'booking':
            return 'Book a meeting';
        case 'handoff':
            return 'Talk to a person';
        case 'ai':
            return 'AI answers';
        case 'score':
            return 'Lead score';
        case 'tag':
            return 'Tag';
        case 'assign':
            return 'Salesperson';
        case 'condition':
            return 'If…';
        case 'end':
            return node.outcome === 'meeting' ? 'Finish: meeting' : node.outcome === 'support' ? 'Finish: support ticket' : 'Finish: lead';
        default:
            return node.type;
    }
}
