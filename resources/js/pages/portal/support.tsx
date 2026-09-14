import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portal', href: '/portal' },
    { title: 'Support', href: '/portal/support' },
];

type Message = {
    id: number;
    body: string;
    user_id: number | null;
    created_at: string | null;
};

type Ticket = {
    id: number;
    subject: string;
    status: string;
    priority: string;
    created_at: string | null;
    messages: Message[];
};

type PortalFile = {
    id: number;
    name: string;
    size: number | null;
    created_at: string | null;
};

const priorities = ['low', 'normal', 'high', 'urgent'];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

const humanize = (value: string) => value.replace(/_/g, ' ');

function formatTime(iso: string | null): string {
    return iso === null ? '—' : new Date(iso).toLocaleString();
}

function formatSize(bytes: number | null): string {
    if (bytes === null) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'resolved' || status === 'closed') return 'default';
    if (status === 'pending') return 'secondary';

    return 'outline';
}

function NewRequestDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ subject: string; body: string; priority: string }>({ subject: '', body: '', priority: 'normal' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('portal.support.tickets.store'), {
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
                <Button>New support request</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New support request</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="portal_ticket_subject">Subject</Label>
                        <Input id="portal_ticket_subject" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                        <InputError message={form.errors.subject} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="portal_ticket_body">What do you need?</Label>
                        <textarea
                            id="portal_ticket_body"
                            className={textareaClass}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="portal_ticket_priority">Priority</Label>
                        <Select value={form.data.priority} onValueChange={(v) => form.setData('priority', v)}>
                            <SelectTrigger id="portal_ticket_priority">
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
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Send request
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PortalSupport({ tickets, files }: { tickets: Ticket[]; files: PortalFile[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Support" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Support" description="Your requests, their replies, and the files shared with you" />
                    <NewRequestDialog />
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Your requests</h3>
                    {tickets.length === 0 ? (
                        <p className="text-muted-foreground text-sm">You have not raised any support requests.</p>
                    ) : (
                        <div className="space-y-3">
                            {tickets.map((ticket) => (
                                <Card key={ticket.id}>
                                    <CardContent className="space-y-3 p-4">
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium">{ticket.subject}</p>
                                                <Badge variant={statusVariant(ticket.status)}>{humanize(ticket.status)}</Badge>
                                                <Badge variant="outline">{ticket.priority}</Badge>
                                            </div>
                                            <p className="text-muted-foreground text-sm">Raised {formatTime(ticket.created_at)}</p>
                                        </div>

                                        {ticket.messages.length === 0 ? (
                                            <p className="text-muted-foreground text-sm">No replies yet.</p>
                                        ) : (
                                            <div className="divide-y rounded-lg border">
                                                {ticket.messages.map((message) => (
                                                    <div key={message.id} className="p-3">
                                                        <p className="text-muted-foreground mb-1 text-xs">{formatTime(message.created_at)}</p>
                                                        <p className="text-sm whitespace-pre-line">{message.body}</p>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Files</h3>
                    {files.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No files have been shared with you.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">File</th>
                                        <th className="p-3">Size</th>
                                        <th className="p-3">Added</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {files.map((file) => (
                                        <tr key={file.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{file.name}</td>
                                            <td className="text-muted-foreground p-3">{formatSize(file.size)}</td>
                                            <td className="text-muted-foreground p-3">{formatTime(file.created_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
