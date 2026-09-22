import type { FlowNode } from '@/lib/flow-tree';
import {
    Building2,
    CalendarClock,
    Flag,
    GitBranch,
    Hash,
    Headphones,
    ListChecks,
    type LucideIcon,
    Mail,
    MessageSquareText,
    Phone,
    Play,
    Sparkles,
    Tag,
    Target,
    TextCursorInput,
    User,
    UserCheck,
} from 'lucide-react';

/**
 * How each kind of step looks on the canvas: a tinted card, a solid icon tile,
 * and a colour for its line label and its mini-map dot. Shared by the block
 * library and the canvas, so a block and the step it becomes look the same.
 * Colour is a cue only - every step also carries its name in words.
 */

export type Tone = {
    /** Card tint and border, for small chips. */
    card: string;
    /** Canvas card border. */
    edge: string;
    /** Canvas card header tint (the body stays plain, for reading and editing). */
    head: string;
    /** Solid icon tile. */
    tile: string;
    /** Label pill on the line leading into a step of this kind. */
    pill: string;
    /** Mini-map colour. */
    dot: string;
};

const TONES = {
    violet: {
        card: 'border-violet-200 bg-violet-50 dark:border-violet-500/35 dark:bg-violet-500/10',
        edge: 'border-violet-200 dark:border-violet-500/35',
        head: 'bg-violet-50 dark:bg-violet-500/10',
        tile: 'bg-violet-500',
        pill: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/40 dark:bg-violet-500/15 dark:text-violet-300',
        dot: '#8b5cf6',
    },
    blue: {
        card: 'border-blue-200 bg-blue-50 dark:border-blue-500/35 dark:bg-blue-500/10',
        edge: 'border-blue-200 dark:border-blue-500/35',
        head: 'bg-blue-50 dark:bg-blue-500/10',
        tile: 'bg-blue-600',
        pill: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/40 dark:bg-blue-500/15 dark:text-blue-300',
        dot: '#2563eb',
    },
    rose: {
        card: 'border-rose-200 bg-rose-50 dark:border-rose-500/35 dark:bg-rose-500/10',
        edge: 'border-rose-200 dark:border-rose-500/35',
        head: 'bg-rose-50 dark:bg-rose-500/10',
        tile: 'bg-rose-500',
        pill: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-300',
        dot: '#f43f5e',
    },
    amber: {
        card: 'border-amber-200 bg-amber-50 dark:border-amber-500/35 dark:bg-amber-500/10',
        edge: 'border-amber-200 dark:border-amber-500/35',
        head: 'bg-amber-50 dark:bg-amber-500/10',
        tile: 'bg-amber-500',
        pill: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-300',
        dot: '#f59e0b',
    },
    emerald: {
        card: 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/35 dark:bg-emerald-500/10',
        edge: 'border-emerald-200 dark:border-emerald-500/35',
        head: 'bg-emerald-50 dark:bg-emerald-500/10',
        tile: 'bg-emerald-500',
        pill: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300',
        dot: '#10b981',
    },
    fuchsia: {
        card: 'border-fuchsia-200 bg-fuchsia-50 dark:border-fuchsia-500/35 dark:bg-fuchsia-500/10',
        edge: 'border-fuchsia-200 dark:border-fuchsia-500/35',
        head: 'bg-fuchsia-50 dark:bg-fuchsia-500/10',
        tile: 'bg-fuchsia-500',
        pill: 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700 dark:border-fuchsia-500/40 dark:bg-fuchsia-500/15 dark:text-fuchsia-300',
        dot: '#d946ef',
    },
    teal: {
        card: 'border-teal-200 bg-teal-50 dark:border-teal-500/35 dark:bg-teal-500/10',
        edge: 'border-teal-200 dark:border-teal-500/35',
        head: 'bg-teal-50 dark:bg-teal-500/10',
        tile: 'bg-teal-600',
        pill: 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-500/40 dark:bg-teal-500/15 dark:text-teal-300',
        dot: '#0d9488',
    },
    cyan: {
        card: 'border-cyan-200 bg-cyan-50 dark:border-cyan-500/35 dark:bg-cyan-500/10',
        edge: 'border-cyan-200 dark:border-cyan-500/35',
        head: 'bg-cyan-50 dark:bg-cyan-500/10',
        tile: 'bg-cyan-600',
        pill: 'border-cyan-200 bg-cyan-50 text-cyan-700 dark:border-cyan-500/40 dark:bg-cyan-500/15 dark:text-cyan-300',
        dot: '#0891b2',
    },
    slate: {
        card: 'border-slate-200 bg-slate-50 dark:border-slate-500/35 dark:bg-slate-500/10',
        edge: 'border-slate-200 dark:border-slate-500/35',
        head: 'bg-slate-50 dark:bg-slate-500/10',
        tile: 'bg-slate-500',
        pill: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/40 dark:bg-slate-500/15 dark:text-slate-300',
        dot: '#64748b',
    },
} satisfies Record<string, Tone>;

export type Visual = { icon: LucideIcon; tone: Tone };

const BY_BLOCK: Record<string, Visual> = {
    start: { icon: Play, tone: TONES.emerald },
    message: { icon: MessageSquareText, tone: TONES.violet },
    question: { icon: ListChecks, tone: TONES.blue },
    open: { icon: TextCursorInput, tone: TONES.rose },
    number: { icon: Hash, tone: TONES.rose },
    first_name: { icon: User, tone: TONES.rose },
    last_name: { icon: User, tone: TONES.rose },
    email: { icon: Mail, tone: TONES.rose },
    phone: { icon: Phone, tone: TONES.rose },
    company: { icon: Building2, tone: TONES.rose },
    booking: { icon: CalendarClock, tone: TONES.amber },
    handoff: { icon: Headphones, tone: TONES.emerald },
    ai: { icon: Sparkles, tone: TONES.fuchsia },
    score: { icon: Target, tone: TONES.teal },
    tag: { icon: Tag, tone: TONES.teal },
    assign: { icon: UserCheck, tone: TONES.teal },
    condition: { icon: GitBranch, tone: TONES.cyan },
    end_lead: { icon: Flag, tone: TONES.rose },
    end_meeting: { icon: Flag, tone: TONES.rose },
    end_support: { icon: Flag, tone: TONES.rose },
};

export const NEUTRAL_TONE: Tone = TONES.slate;

export function blockVisual(key: string): Visual {
    return BY_BLOCK[key] ?? BY_BLOCK.message;
}

/** The block a saved step corresponds to, for its icon and colour. */
export function stepVisual(node: FlowNode): Visual {
    switch (node.type) {
        case 'choice':
            return BY_BLOCK.question;
        case 'input': {
            const byField: Record<string, string> = {
                first_name: 'first_name',
                last_name: 'last_name',
                email: 'email',
                phone: 'phone',
                company_name: 'company',
            };
            if (node.field && byField[node.field]) return BY_BLOCK[byField[node.field]];
            if (node.input === 'email') return BY_BLOCK.email;
            if (node.input === 'phone') return BY_BLOCK.phone;
            return node.input === 'number' ? BY_BLOCK.number : BY_BLOCK.open;
        }
        case 'end':
            return BY_BLOCK.end_lead;
        default:
            return BY_BLOCK[node.type] ?? BY_BLOCK.message;
    }
}

/** A solid icon tile, as on the canvas cards and in the step list. */
export function StepIcon({ visual, className = 'size-9' }: { visual: Visual; className?: string }) {
    const Icon = visual.icon;
    return (
        <span className={`flex shrink-0 items-center justify-center rounded-lg text-white shadow-sm ${visual.tone.tile} ${className}`} aria-hidden>
            <Icon className="size-4" />
        </span>
    );
}
