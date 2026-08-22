import { EmptyState } from '@/components/empty-state';
import { InitialAvatar } from '@/components/initial-avatar';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Flame, Inbox } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Website Chat', href: '/chat' }];

type Conversation = {
    id: number;
    status: string;
    lead_score: number;
    widget: string | null;
    assignee: string | null;
    contact: { name: string; email: string } | null;
    answers: Record<string, string>;
    priority: boolean;
    last_message_at: string | null;
};

const FILTERS = [
    { id: 'all', label: 'All' },
    { id: 'unassigned', label: 'Unassigned' },
    { id: 'mine', label: 'Mine' },
    { id: 'open', label: 'Open' },
    { id: 'closed', label: 'Closed' },
];

/** Score bands from the sales scoring service: 60+ hot, 30+ warm. */
function temperature(score: number): { label: string; className: string } | null {
    if (score >= 60) return { label: 'Hot', className: 'bg-red-500/10 text-red-600 dark:text-red-400' };
    if (score >= 30) return { label: 'Warm', className: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' };
    return null;
}

function since(value: string | null): string {
    if (!value) return '';
    const minutes = Math.round((Date.now() - new Date(value).getTime()) / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m`;
    if (minutes < 1440) return `${Math.round(minutes / 60)}h`;
    return `${Math.round(minutes / 1440)}d`;
}

export default function ChatInbox({ conversations, filter }: { conversations: Conversation[]; filter: string }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Conversations" />
            <div className="space-y-4 p-4">
                <PageHeader title="Conversations" description="Every chat your website widgets have started, newest first.">
                    <div className="flex flex-wrap gap-2">
                        {FILTERS.map((f) => (
                            <Button
                                key={f.id}
                                size="sm"
                                variant={filter === f.id ? 'default' : 'outline'}
                                onClick={() => router.get(route('chat.inbox'), f.id === 'all' ? {} : { filter: f.id }, { preserveState: true })}
                            >
                                {f.label}
                            </Button>
                        ))}
                    </div>
                </PageHeader>

                {conversations.length === 0 ? (
                    <EmptyState
                        icon={Inbox}
                        title="No conversations yet"
                        description="Once your widget is live on your website, visitor conversations will appear here."
                        action={
                            <Button asChild variant="outline">
                                <Link href={route('chat.widgets.index')}>Set up a widget</Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="border-border divide-border divide-y overflow-hidden rounded-lg border">
                        {conversations.map((c) => {
                            const temp = temperature(c.lead_score);
                            const name = c.contact?.name || 'Anonymous visitor';

                            return (
                                <Link
                                    key={c.id}
                                    href={route('chat.conversations.show', c.id)}
                                    className="hover:bg-muted/40 flex items-center gap-3 px-4 py-3 transition-colors"
                                >
                                    <InitialAvatar name={name} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">{name}</span>
                                            {c.priority && (
                                                <Badge className="gap-1 bg-red-500/10 text-red-600 dark:text-red-400">
                                                    <Flame className="size-3" aria-hidden /> Priority
                                                </Badge>
                                            )}
                                            {temp && (
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${temp.className}`}>
                                                    {temp.label}
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-muted-foreground truncate text-sm">
                                            {c.answers.company_name || c.contact?.email || c.widget || 'Website chat'}
                                        </div>
                                    </div>
                                    <div className="shrink-0 text-right">
                                        <Badge variant="secondary" className="capitalize">
                                            {c.status}
                                        </Badge>
                                        <div className="text-muted-foreground mt-1 text-xs tabular-nums">
                                            {c.assignee ?? 'Unassigned'} · {since(c.last_message_at)}
                                        </div>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
