import type { FlowNode } from '@/lib/flow-tree';
import {
    Building2,
    CalendarClock,
    CircleDot,
    Flag,
    GitBranch,
    Hash,
    Headphones,
    type LucideIcon,
    Mail,
    MessageSquare,
    Phone,
    Sparkles,
    Tag,
    Target,
    Type,
    User,
    UserCheck,
} from 'lucide-react';

/**
 * How each kind of step looks, shared by the block library and the tree so a
 * block and the step it becomes are recognisably the same thing. Colour is a
 * group cue only - every step also carries its name in words.
 */

type Visual = { icon: LucideIcon; tone: string };

const TONES = {
    say: 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
    ask: 'bg-brand-soft text-brand-strong',
    contact: 'bg-violet-500/12 text-violet-700 dark:text-violet-300',
    action: 'bg-amber-500/14 text-amber-700 dark:text-amber-300',
    finish: 'bg-slate-500/12 text-slate-700 dark:text-slate-300',
};

const BY_BLOCK: Record<string, Visual> = {
    message: { icon: MessageSquare, tone: TONES.say },
    question: { icon: CircleDot, tone: TONES.ask },
    open: { icon: Type, tone: TONES.ask },
    number: { icon: Hash, tone: TONES.ask },
    first_name: { icon: User, tone: TONES.contact },
    last_name: { icon: User, tone: TONES.contact },
    email: { icon: Mail, tone: TONES.contact },
    phone: { icon: Phone, tone: TONES.contact },
    company: { icon: Building2, tone: TONES.contact },
    booking: { icon: CalendarClock, tone: TONES.action },
    handoff: { icon: Headphones, tone: TONES.action },
    ai: { icon: Sparkles, tone: TONES.action },
    score: { icon: Target, tone: TONES.action },
    tag: { icon: Tag, tone: TONES.action },
    assign: { icon: UserCheck, tone: TONES.action },
    condition: { icon: GitBranch, tone: TONES.action },
    end_lead: { icon: Flag, tone: TONES.finish },
    end_meeting: { icon: Flag, tone: TONES.finish },
    end_support: { icon: Flag, tone: TONES.finish },
};

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

export function StepIcon({ visual, className = 'size-8' }: { visual: Visual; className?: string }) {
    const Icon = visual.icon;
    return (
        <span className={`flex shrink-0 items-center justify-center rounded-lg ${visual.tone} ${className}`} aria-hidden>
            <Icon className="size-4" />
        </span>
    );
}
