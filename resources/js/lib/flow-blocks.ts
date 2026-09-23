import type { FlowNode } from './flow-tree';

/**
 * The step library: every step an owner can drag into a conversation, ready
 * to use. Contact steps already save to the CRM fields the chat's capture
 * reads, so nobody types "first_name" - and Email and First Name start out
 * required, the rest optional, because those two are what a lead needs.
 */

export type BlockGroup = 'Message & Interaction' | 'Contact Details' | 'Logic & Flow' | 'Actions' | 'End';

export type Block = {
    key: string;
    group: BlockGroup;
    label: string;
    hint: string;
    make: () => FlowNode;
};

export const BLOCK_GROUPS: BlockGroup[] = ['Message & Interaction', 'Contact Details', 'Logic & Flow', 'Actions', 'End'];

/** The contact fields the capture service copies onto the CRM contact and lead. */
export const CONTACT_FIELDS: Record<string, string> = {
    first_name: 'First Name',
    last_name: 'Last Name',
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
        group: 'Message & Interaction',
        label: 'Send Message',
        hint: 'Say something, then carry on',
        make: () => ({ type: 'message', text: 'Thanks for stopping by!' }),
    },
    {
        key: 'question',
        group: 'Message & Interaction',
        label: 'Ask a Question',
        hint: 'Buttons to tap; each answer can lead its own way',
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
        group: 'Message & Interaction',
        label: 'Collect User Input',
        hint: 'Visitors type their own answer',
        make: () => ({ type: 'input', input: 'text', text: 'Tell us a little about what you need.', optional: false }),
    },
    {
        key: 'number',
        group: 'Message & Interaction',
        label: 'Collect a Number',
        hint: 'A number, such as team size',
        make: () => ({ type: 'input', input: 'number', text: 'How many people work at your company?', optional: false }),
    },

    {
        key: 'first_name',
        group: 'Contact Details',
        label: 'First Name',
        hint: 'Saved to the lead',
        make: () => contact('first_name', 'text', 'What is your first name?', true),
    },
    {
        key: 'last_name',
        group: 'Contact Details',
        label: 'Last Name',
        hint: 'Saved to the lead',
        make: () => contact('last_name', 'text', 'And your last name?', false),
    },
    {
        key: 'email',
        group: 'Contact Details',
        label: 'Email',
        hint: 'Checked as a real address',
        make: () => contact('email', 'email', 'What is the best email to reach you?', true),
    },
    {
        key: 'phone',
        group: 'Contact Details',
        label: 'Phone',
        hint: 'Checked as a phone number',
        make: () => contact('phone', 'phone', 'What is the best number to reach you?', false),
    },
    {
        key: 'company',
        group: 'Contact Details',
        label: 'Company',
        hint: 'Saved to the lead',
        make: () => contact('company_name', 'company', 'What company are you with?', false),
    },

    {
        key: 'condition',
        group: 'Logic & Flow',
        label: 'Condition',
        hint: 'Go one way or another on an earlier answer',
        make: () => ({ type: 'condition', field: '', operator: 'equals', value: '' }),
    },
    {
        key: 'score',
        group: 'Logic & Flow',
        label: 'Lead Score',
        hint: 'Mark this path as a stronger lead',
        make: () => ({ type: 'score', points: 10 }),
    },
    {
        key: 'tag',
        group: 'Logic & Flow',
        label: 'Add Tag',
        hint: 'Label the conversation',
        make: () => ({ type: 'tag', tag: 'interested' }),
    },
    {
        key: 'webhook',
        group: 'Actions',
        label: 'Send to Your System',
        hint: 'Post the answers to your PSA, Zapier or your own API',
        make: () => ({ type: 'webhook', url: '', field: '', path: '' }),
    },
    {
        key: 'assign',
        group: 'Logic & Flow',
        label: 'Assign Salesperson',
        hint: 'Choose who follows up',
        make: () => ({ type: 'assign', assignee_id: null }),
    },

    {
        key: 'booking',
        group: 'Actions',
        label: 'Book a Meeting',
        hint: 'Offers your free times as buttons',
        make: () => ({ type: 'booking', text: 'Pick a time that suits you:' }),
    },
    {
        key: 'handoff',
        group: 'Actions',
        label: 'Human Handoff',
        hint: 'Connects someone from your team',
        make: () => ({ type: 'handoff' }),
    },
    {
        key: 'ai',
        group: 'Actions',
        label: 'AI Answers',
        hint: 'Answers questions about your business',
        make: () => ({ type: 'ai', text: 'What would you like to know?' }),
    },

    {
        key: 'end_lead',
        group: 'End',
        label: 'End: New Lead',
        hint: 'Saves them as a lead',
        make: () => ({ type: 'end', outcome: 'lead', text: 'Thanks! We will be in touch shortly.' }),
    },
    {
        key: 'end_meeting',
        group: 'End',
        label: 'End: Book a Meeting',
        hint: 'Saves the lead and offers your booking page',
        make: () => ({ type: 'end', outcome: 'meeting', text: 'Great, pick a time that suits you.' }),
    },
    {
        key: 'end_support',
        group: 'End',
        label: 'End: Support Ticket',
        hint: 'Existing customers: a ticket, never a lead',
        make: () => ({ type: 'end', outcome: 'support', text: 'Thanks, we have opened a support ticket and will reply by email.' }),
    },
];

export function blockByKey(key: string): Block | undefined {
    return BLOCKS.find((b) => b.key === key);
}

/** The step's title on its card: "Send Message", "Collect Email", "Human Handoff". */
export function stepKind(node: FlowNode): string {
    switch (node.type) {
        case 'webhook':
            return 'Send to Your System';
        case 'message':
            return 'Send Message';
        case 'choice':
            return 'Ask a Question';
        case 'input':
            if (node.field && CONTACT_FIELDS[node.field]) return `Collect ${CONTACT_FIELDS[node.field]}`;
            return node.input === 'number'
                ? 'Collect a Number'
                : node.input === 'email'
                  ? 'Collect Email'
                  : node.input === 'phone'
                    ? 'Collect Phone'
                    : 'Collect User Input';
        case 'booking':
            return 'Book a Meeting';
        case 'handoff':
            return 'Human Handoff';
        case 'ai':
            return 'AI Answers';
        case 'score':
            return 'Lead Score';
        case 'tag':
            return 'Add Tag';
        case 'assign':
            return 'Assign Salesperson';
        case 'condition':
            return 'Condition';
        case 'end':
            return 'End';
        default:
            return node.type;
    }
}

/** What an End step does, in words, for its card. */
export function outcomeLabel(outcome: string | undefined): string {
    return outcome === 'meeting' ? 'Saves the lead, offers a meeting' : outcome === 'support' ? 'Opens a support ticket' : 'Saves them as a lead';
}
