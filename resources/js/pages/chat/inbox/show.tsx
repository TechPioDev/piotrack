import { InitialAvatar } from '@/components/initial-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Flame, Lock, Radio } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

type Message = { id: number; role: string; body: string | null; author: string | null; at: string };
type Conversation = {
    id: number;
    status: string;
    lead_score: number;
    widget: string | null;
    assignee: { id: number; name: string } | null;
    contact: { id: number; name: string; email: string; lead_score: number } | null;
    answers: Record<string, string>;
    priority: boolean;
    is_live: boolean;
    attribution: Record<string, string> | null;
    created_at: string;
};

/** Human labels for the fields the default qualification flow collects. */
const FIELD_LABELS: Record<string, string> = {
    first_name: 'First name',
    last_name: 'Last name',
    email: 'Email',
    phone: 'Phone',
    company_name: 'Company',
    company_size: 'Company size',
    current_provider: 'Has an IT provider',
    challenge: 'Biggest challenge',
    support_topic: 'Support topic',
    wants_meeting: 'Wants a meeting',
};

const ATTRIBUTION_LABELS: Record<string, string> = {
    source: 'Source',
    page: 'Page',
    referrer: 'Referrer',
    utm_source: 'UTM source',
    utm_medium: 'UTM medium',
    utm_campaign: 'UTM campaign',
    utm_term: 'Keyword',
    utm_content: 'UTM content',
};

function label(key: string): string {
    return FIELD_LABELS[key] ?? key.replace(/_/g, ' ');
}

export default function ChatConversationShow({
    conversation,
    messages: initialMessages,
    statuses,
    presence,
}: {
    conversation: Conversation;
    messages: Message[];
    statuses: string[];
    presence: { me: string; roster: { id: number; name: string; status: string }[] };
}) {
    const { can } = usePermissions();
    const reply = useForm({ body: '' });
    const note = useForm({ body: '' });
    const [messages, setMessages] = useState<Message[]>(initialMessages);
    const [isLive, setIsLive] = useState(conversation.is_live);
    const logRef = useRef<HTMLDivElement>(null);

    // Inertia re-renders on reply/note; keep local state in step with the server.
    useEffect(() => setMessages(initialMessages), [initialMessages]);

    // A live conversation is a person waiting for an answer, so poll for the
    // visitor's replies rather than making the agent reload the page.
    useEffect(() => {
        const id = window.setInterval(async () => {
            const since = messages.length > 0 ? messages[messages.length - 1].id : 0;
            try {
                const response = await fetch(route('chat.conversations.poll', conversation.id) + `?since=${since}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) return;
                const data = (await response.json()) as { messages: Message[]; is_live: boolean };
                setIsLive(data.is_live);
                if (data.messages.length > 0) {
                    setMessages((current) => [...current, ...data.messages]);
                }
            } catch {
                /* transient failures are not worth surfacing to the agent */
            }
        }, 5000);
        return () => window.clearInterval(id);
    }, [conversation.id, messages]);

    // Keep the newest message in view as the conversation grows.
    useEffect(() => {
        const log = logRef.current;
        if (log) log.scrollTop = log.scrollHeight;
    }, [messages]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Website Chat', href: '/chat' },
        { title: conversation.contact?.name ?? 'Conversation', href: `/chat/conversations/${conversation.id}` },
    ];

    const sendReply: FormEventHandler = (e) => {
        e.preventDefault();
        reply.post(route('chat.conversations.reply', conversation.id), {
            preserveScroll: true,
            onSuccess: () => reply.reset(),
        });
    };

    const sendNote: FormEventHandler = (e) => {
        e.preventDefault();
        note.post(route('chat.conversations.note', conversation.id), {
            preserveScroll: true,
            onSuccess: () => note.reset(),
        });
    };

    const name = conversation.contact?.name ?? 'Anonymous visitor';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={name} />
            <div className="grid gap-4 p-4 lg:grid-cols-3">
                {/* Transcript */}
                <div className="lg:col-span-2">
                    <div className="border-border bg-card flex h-[calc(100vh-9rem)] flex-col overflow-hidden rounded-lg border">
                        <div className="border-border flex items-center gap-3 border-b px-4 py-3">
                            <InitialAvatar name={name} />
                            <div className="min-w-0">
                                <div className="flex items-center gap-2 font-medium">
                                    {name}
                                    {isLive && (
                                        <Badge className="gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                            <Radio className="size-3" aria-hidden /> Live
                                        </Badge>
                                    )}
                                    {conversation.priority && (
                                        <Badge className="gap-1 bg-red-500/10 text-red-600 dark:text-red-400">
                                            <Flame className="size-3" aria-hidden /> Priority
                                        </Badge>
                                    )}
                                </div>
                                <div className="text-muted-foreground truncate text-xs">
                                    {conversation.widget ?? 'Website chat'} · {new Date(conversation.created_at).toLocaleString()}
                                </div>
                            </div>
                        </div>

                        <div ref={logRef} className="flex-1 space-y-3 overflow-y-auto p-4">
                            {messages.map((m) => {
                                if (m.role === 'note') {
                                    return (
                                        <div key={m.id} className="mx-auto max-w-[90%] rounded-lg border border-amber-500/30 bg-amber-500/10 p-3">
                                            <div className="flex items-center gap-1.5 text-xs font-semibold text-amber-700 dark:text-amber-400">
                                                <Lock className="size-3" aria-hidden /> Internal note · {m.author}
                                            </div>
                                            <p className="text-foreground mt-1 text-sm whitespace-pre-wrap">{m.body}</p>
                                        </div>
                                    );
                                }

                                if (m.role === 'system') {
                                    return (
                                        <p key={m.id} className="text-muted-foreground text-center text-xs">
                                            {m.body}
                                        </p>
                                    );
                                }

                                const fromVisitor = m.role === 'visitor';
                                return (
                                    <div key={m.id} className={`flex ${fromVisitor ? 'justify-end' : 'justify-start'}`}>
                                        <div
                                            className={`max-w-[80%] rounded-2xl px-3.5 py-2.5 text-sm whitespace-pre-wrap ${
                                                fromVisitor
                                                    ? 'bg-brand text-brand-foreground rounded-br-sm'
                                                    : 'bg-muted text-foreground rounded-bl-sm'
                                            }`}
                                        >
                                            {m.body}
                                            {m.role === 'agent' && m.author && <div className="mt-1 text-[11px] opacity-70">{m.author}</div>}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {can('chat.inbox.handle') && (
                            <div className="border-border space-y-2 border-t p-3">
                                <form onSubmit={sendReply} className="flex gap-2">
                                    <Input
                                        value={reply.data.body}
                                        onChange={(e) => reply.setData('body', e.target.value)}
                                        placeholder="Reply to the visitor…"
                                    />
                                    <Button type="submit" disabled={reply.processing || !reply.data.body.trim()}>
                                        Send
                                    </Button>
                                </form>
                                <form onSubmit={sendNote} className="flex gap-2">
                                    <Input
                                        value={note.data.body}
                                        onChange={(e) => note.setData('body', e.target.value)}
                                        placeholder={`Internal note — @mention a colleague (${presence.roster.length} in this workspace)…`}
                                    />
                                    <Button type="submit" variant="outline" disabled={note.processing || !note.data.body.trim()}>
                                        Note
                                    </Button>
                                </form>
                            </div>
                        )}
                    </div>
                </div>

                {/* Lead details rail */}
                <div className="space-y-4">
                    <div className="border-border bg-card rounded-lg border p-4">
                        <div className="flex items-baseline justify-between">
                            <span className="text-muted-foreground text-sm font-medium">Lead score</span>
                            <span className="text-brand-strong text-3xl font-semibold tabular-nums">{conversation.lead_score}</span>
                        </div>
                        <div className="mt-3 space-y-2">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground text-sm">Status</span>
                                {can('chat.inbox.handle') ? (
                                    <Select
                                        value={conversation.status}
                                        onValueChange={(v) =>
                                            router.patch(route('chat.conversations.update', conversation.id), { status: v }, { preserveScroll: true })
                                        }
                                    >
                                        <SelectTrigger className="h-8 w-40">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {statuses.map((s) => (
                                                <SelectItem key={s} value={s} className="capitalize">
                                                    {s}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <Badge variant="secondary" className="capitalize">
                                        {conversation.status}
                                    </Badge>
                                )}
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Owner</span>
                                <span className="text-sm">{conversation.assignee?.name ?? 'Unassigned'}</span>
                            </div>
                        </div>
                        {conversation.contact && (
                            <Button asChild variant="outline" className="mt-4 w-full">
                                <Link href={route('crm.contacts.show', conversation.contact.id)}>Open in CRM</Link>
                            </Button>
                        )}
                    </div>

                    {Object.keys(conversation.answers).length > 0 && (
                        <div className="border-border bg-card rounded-lg border">
                            <div className="border-border border-b px-4 py-2.5">
                                <h2 className="text-foreground text-sm font-semibold">What they told us</h2>
                            </div>
                            <dl className="divide-border divide-y text-sm">
                                {Object.entries(conversation.answers).map(([key, value]) => (
                                    <div key={key} className="flex justify-between gap-4 px-4 py-2">
                                        <dt className="text-muted-foreground capitalize">{label(key)}</dt>
                                        <dd className="text-right font-medium break-words">{String(value).replace(/_/g, ' ')}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}

                    {conversation.attribution && Object.keys(conversation.attribution).length > 0 && (
                        <div className="border-border bg-card rounded-lg border">
                            <div className="border-border border-b px-4 py-2.5">
                                <h2 className="text-foreground text-sm font-semibold">Where they came from</h2>
                            </div>
                            <dl className="divide-border divide-y text-sm">
                                {Object.entries(conversation.attribution).map(([key, value]) => (
                                    <div key={key} className="flex justify-between gap-4 px-4 py-2">
                                        <dt className="text-muted-foreground">{ATTRIBUTION_LABELS[key] ?? key}</dt>
                                        <dd className="max-w-[60%] truncate text-right font-medium" title={String(value)}>
                                            {String(value)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
