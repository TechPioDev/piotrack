import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Support', href: '/support' }];

type TicketMessage = {
    id: number;
    body: string;
    is_internal: boolean;
    created_at: string | null;
};

type TicketAttachment = { id: number; name: string; size: number };

type Ticket = {
    id: number;
    subject: string;
    body: string;
    status: string;
    priority: string;
    category: string | null;
    assignee_id: number | null;
    resolved_at: string | null;
    messages: TicketMessage[];
    attachments: TicketAttachment[];
};

type Article = {
    id: number;
    title: string;
    slug: string;
    category: string | null;
    excerpt: string | null;
    is_published: boolean;
};

type Announcement = {
    id: number;
    title: string;
    body: string;
    type: string;
    published_at: string | null;
};

type User = { id: number; name: string };

const priorities = ['low', 'normal', 'high', 'urgent'];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

const humanize = (value: string) => value.replace(/_/g, ' ');

function formatTime(iso: string | null): string {
    return iso === null ? '—' : new Date(iso).toLocaleString();
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'resolved' || status === 'closed') return 'default';
    if (status === 'pending') return 'secondary';

    return 'outline';
}

function priorityVariant(priority: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (priority === 'urgent') return 'destructive';
    if (priority === 'high') return 'secondary';

    return 'outline';
}

function NewTicketDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ subject: string; body: string; priority: string; category: string }>({
        subject: '',
        body: '',
        priority: 'normal',
        category: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('support.tickets.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>New ticket</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New ticket</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="ticket_subject">Subject</Label>
                        <Input id="ticket_subject" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                        <InputError message={form.errors.subject} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="ticket_body">Description</Label>
                        <textarea
                            id="ticket_body"
                            className={textareaClass}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="ticket_priority">Priority</Label>
                            <Select value={form.data.priority} onValueChange={(v) => form.setData('priority', v)}>
                                <SelectTrigger id="ticket_priority">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {priorities.map((priority) => (
                                        <SelectItem key={priority} value={priority}>
                                            {priority}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.priority} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="ticket_category">Category</Label>
                            <Input id="ticket_category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} />
                            <InputError message={form.errors.category} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Open ticket
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NewArticleDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ title: string; category: string; excerpt: string; body: string; is_published: boolean }>({
        title: '',
        category: '',
        excerpt: '',
        body: '',
        is_published: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('support.articles.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    New article
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New help centre article</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="article_title">Title</Label>
                        <Input id="article_title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="article_category">Category</Label>
                        <Input id="article_category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} />
                        <InputError message={form.errors.category} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="article_excerpt">Excerpt</Label>
                        <Input id="article_excerpt" value={form.data.excerpt} onChange={(e) => form.setData('excerpt', e.target.value)} />
                        <InputError message={form.errors.excerpt} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="article_body">Body</Label>
                        <textarea
                            id="article_body"
                            className={textareaClass}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <label className="flex items-start gap-3 rounded-lg border p-3" htmlFor="article_published">
                        <Checkbox
                            id="article_published"
                            checked={form.data.is_published}
                            onCheckedChange={(v) => form.setData('is_published', v === true)}
                        />
                        <span className="text-sm">
                            <span className="font-medium">Publish</span>
                            <span className="text-muted-foreground block">Unpublished articles stay internal.</span>
                        </span>
                    </label>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * A reply is either sent to the requester or kept as an internal note. The
 * choice is stated in plain words because getting it wrong sends internal
 * commentary to a customer.
 */
/** SUPP-002: attach a document through the scanned, tenant-checked file store. */
function AttachmentForm({ ticket }: { ticket: Ticket }) {
    const form = useForm<{ file: File | null; attachable_type: string; attachable_id: number }>({
        file: null,
        attachable_type: 'ticket',
        attachable_id: ticket.id,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('files.store'), { preserveScroll: true, onSuccess: () => form.reset('file') });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-center gap-2">
            <Input
                id={`ticket_file_${ticket.id}`}
                type="file"
                className="h-9 max-w-72 text-xs"
                onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
            />
            <Button type="submit" size="sm" variant="outline" disabled={form.processing || form.data.file === null}>
                Attach
            </Button>
            <InputError message={form.errors.file} />
        </form>
    );
}

function ReplyForm({ ticket }: { ticket: Ticket }) {
    const form = useForm<{ body: string; is_internal: boolean }>({ body: '', is_internal: false });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('support.tickets.reply', ticket.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-2 border-t pt-3">
            <Label htmlFor={`reply_body_${ticket.id}`}>Reply</Label>
            <textarea
                id={`reply_body_${ticket.id}`}
                className={textareaClass}
                value={form.data.body}
                onChange={(e) => form.setData('body', e.target.value)}
            />
            <InputError message={form.errors.body} />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <label className="flex items-center gap-2 text-sm" htmlFor={`reply_internal_${ticket.id}`}>
                    <Checkbox
                        id={`reply_internal_${ticket.id}`}
                        checked={form.data.is_internal}
                        onCheckedChange={(v) => form.setData('is_internal', v === true)}
                    />
                    <span>
                        Internal note
                        <span className="text-muted-foreground block text-xs">
                            {form.data.is_internal ? 'Kept internal — the client portal never shows this.' : 'The requester will see this reply.'}
                        </span>
                    </span>
                </label>
                <Button type="submit" size="sm" disabled={form.processing || form.data.body.trim() === ''}>
                    Send
                </Button>
            </div>
        </form>
    );
}

export default function DeliverySupport({
    tickets,
    articles,
    announcements,
    members,
}: {
    tickets: Ticket[];
    articles: Article[];
    announcements: Announcement[];
    members: User[];
}) {
    const { can } = usePermissions();
    const canManage = can('support.manage');
    // Attaching goes through the file store, which has its own permission.
    const canAttach = can('files.manage');

    const assign = (ticket: Ticket, userId: string) =>
        router.post(route('support.tickets.assign', ticket.id), { user_id: Number(userId) }, { preserveScroll: true });
    const resolve = (ticket: Ticket) => router.post(route('support.tickets.resolve', ticket.id), {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Support" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Support" description="Tickets, the help centre and the announcements customers can see" />
                    {canManage && <NewTicketDialog />}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Tickets</h3>
                    {tickets.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No tickets. Requests raised here or from the client portal appear in this list.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {tickets.map((ticket) => (
                                <Card key={ticket.id}>
                                    <CardContent className="space-y-3 p-4">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-medium">{ticket.subject}</p>
                                                    <Badge variant={statusVariant(ticket.status)}>{humanize(ticket.status)}</Badge>
                                                    <Badge variant={priorityVariant(ticket.priority)}>{ticket.priority}</Badge>
                                                    {ticket.category && <Badge variant="outline">{ticket.category}</Badge>}
                                                </div>
                                                <p className="text-muted-foreground text-sm whitespace-pre-line">{ticket.body}</p>
                                                {ticket.resolved_at !== null && (
                                                    <p className="text-muted-foreground text-sm">Resolved {formatTime(ticket.resolved_at)}</p>
                                                )}
                                            </div>
                                            {canManage && (
                                                <div className="flex shrink-0 flex-wrap items-center gap-2">
                                                    <Select
                                                        value={ticket.assignee_id === null ? '' : String(ticket.assignee_id)}
                                                        onValueChange={(v) => assign(ticket, v)}
                                                    >
                                                        <SelectTrigger className="w-44" aria-label={`Assignee for ${ticket.subject}`}>
                                                            <SelectValue placeholder="Unassigned" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {members.map((member) => (
                                                                <SelectItem key={member.id} value={String(member.id)}>
                                                                    {member.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    {ticket.status !== 'resolved' && ticket.status !== 'closed' && (
                                                        <Button size="sm" variant="secondary" onClick={() => resolve(ticket)}>
                                                            Resolve
                                                        </Button>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {ticket.messages.length > 0 && (
                                            <div className="divide-y rounded-lg border">
                                                {ticket.messages.map((message) => (
                                                    <div key={message.id} className={message.is_internal ? 'bg-muted/50 p-3' : 'p-3'}>
                                                        <div className="text-muted-foreground mb-1 flex flex-wrap items-center gap-2 text-xs">
                                                            <span>{formatTime(message.created_at)}</span>
                                                            {message.is_internal && (
                                                                <Badge variant="secondary" className="gap-1">
                                                                    <Lock className="size-3" aria-hidden="true" />
                                                                    Internal note — never shown to the client
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <p className="text-sm whitespace-pre-line">{message.body}</p>
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        {(ticket.attachments.length > 0 || canManage) && (
                                            <div className="space-y-2 border-t pt-3">
                                                <p className="text-sm font-medium">Attachments</p>
                                                {ticket.attachments.length === 0 ? (
                                                    <p className="text-muted-foreground text-xs">No documents attached yet.</p>
                                                ) : (
                                                    <ul className="space-y-1 text-sm">
                                                        {ticket.attachments.map((file) => (
                                                            <li key={file.id} className="flex items-center justify-between gap-2">
                                                                <a href={route('files.download', file.id)} className="hover:underline">
                                                                    {file.name}
                                                                </a>
                                                                <span className="text-muted-foreground text-xs">
                                                                    {Math.round(file.size / 1024)} KB
                                                                </span>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                                {canAttach && <AttachmentForm ticket={ticket} />}
                                            </div>
                                        )}

                                        {canManage && <ReplyForm ticket={ticket} />}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Help centre</h3>
                        {canManage && <NewArticleDialog />}
                    </div>
                    {articles.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No knowledge base articles yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Article</th>
                                        <th className="p-3 font-medium">Category</th>
                                        <th className="p-3 font-medium">Slug</th>
                                        <th className="p-3 font-medium">Published</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {articles.map((article) => (
                                        <tr key={article.id} className="hover:bg-muted/40 align-top">
                                            <td className="max-w-80 p-3">
                                                <p className="font-medium">{article.title}</p>
                                                {article.excerpt && <p className="text-muted-foreground">{article.excerpt}</p>}
                                            </td>
                                            <td className="text-muted-foreground p-3">{article.category ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{article.slug}</td>
                                            <td className="p-3">
                                                {article.is_published ? <Badge>Published</Badge> : <Badge variant="secondary">Draft</Badge>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Announcements</h3>
                    {announcements.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No published announcements.</p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {announcements.map((announcement) => (
                                <div key={announcement.id} className="space-y-1 p-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-sm font-medium">{announcement.title}</p>
                                        <Badge variant="outline">{humanize(announcement.type)}</Badge>
                                        <span className="text-muted-foreground text-xs">{formatTime(announcement.published_at)}</span>
                                    </div>
                                    <p className="text-muted-foreground text-sm whitespace-pre-line">{announcement.body}</p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
