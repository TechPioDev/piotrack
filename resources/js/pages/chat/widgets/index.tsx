import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { copyText } from '@/lib/clipboard';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Copy, MessagesSquare, Settings, Workflow } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Website Chat', href: '/chat' },
    { title: 'Widgets', href: '/chat/widgets' },
];

type Widget = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    public_key: string;
    conversations_count: number;
    embed: string;
};

const statusVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    active: 'default',
    draft: 'secondary',
    paused: 'outline',
};

export default function ChatWidgets({ widgets }: { widgets: Widget[] }) {
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);
    const [copied, setCopied] = useState<number | null>(null);
    const [copyFailed, setCopyFailed] = useState<number | null>(null);
    const form = useForm({ name: '', description: '' });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('chat.widgets.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    const copyEmbed = async (widget: Widget) => {
        const ok = await copyText(widget.embed);
        // Say what actually happened: on a locked-down browser the copy can fail,
        // and claiming success would leave them pasting stale clipboard contents.
        setCopied(ok ? widget.id : null);
        if (!ok) {
            setCopyFailed(widget.id);
            setTimeout(() => setCopyFailed(null), 4000);
            return;
        }
        setTimeout(() => setCopied(null), 2000);
    };

    const setStatus = (widget: Widget, status: string) => {
        router.patch(route('chat.widgets.update', widget.id), { status }, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Chat widgets" />
            <div className="space-y-4 p-4">
                <PageHeader
                    title="Chat widgets"
                    description="Create a conversational widget, then paste one line of code onto your website to start capturing qualified leads."
                    actions={
                        can('chat.widget.manage') && (
                            <Dialog open={open} onOpenChange={setOpen}>
                                <DialogTrigger asChild>
                                    <Button>New widget</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>New chat widget</DialogTitle>
                                    <form onSubmit={create} className="space-y-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="name">Name</Label>
                                            <Input
                                                id="name"
                                                value={form.data.name}
                                                onChange={(e) => form.setData('name', e.target.value)}
                                                placeholder="Homepage Chat"
                                            />
                                            <InputError message={form.errors.name} />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="description">Description</Label>
                                            <Input
                                                id="description"
                                                value={form.data.description}
                                                onChange={(e) => form.setData('description', e.target.value)}
                                                placeholder="Where this widget runs and who it is for"
                                            />
                                        </div>
                                        <p className="text-muted-foreground text-sm">
                                            It starts as a draft with the MSP qualification conversation, so you can review it before it goes live.
                                        </p>
                                        <DialogFooter>
                                            <Button type="submit" disabled={form.processing}>
                                                Create
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )
                    }
                />

                {widgets.length === 0 ? (
                    <EmptyState
                        icon={MessagesSquare}
                        title="No chat widgets yet"
                        description="Create your first widget to start turning website visitors into qualified leads."
                        action={can('chat.widget.manage') && <Button onClick={() => setOpen(true)}>New widget</Button>}
                    />
                ) : (
                    <div className="space-y-3">
                        <Table>
                            <TableHeader>
                                <tr>
                                    <TableHead>Widget</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-center">Conversations</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </tr>
                            </TableHeader>
                            <TableBody>
                                {widgets.map((widget) => (
                                    <TableRow key={widget.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-2.5">
                                                <span className="bg-brand-soft text-brand-strong flex size-8 shrink-0 items-center justify-center rounded-lg">
                                                    <MessagesSquare className="size-4" aria-hidden />
                                                </span>
                                                <div>
                                                    <div className="font-medium">{widget.name}</div>
                                                    {widget.description && <div className="text-muted-foreground text-xs">{widget.description}</div>}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={statusVariant[widget.status] ?? 'secondary'} className="capitalize">
                                                {widget.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">{widget.conversations_count}</TableCell>
                                        <TableCell className="text-right">
                                            {can('chat.widget.manage') && (
                                                <div className="flex justify-end gap-2">
                                                    <Button size="sm" variant="outline" asChild>
                                                        <Link href={route('chat.widgets.edit', widget.id)}>
                                                            <Settings className="size-3.5" aria-hidden /> Settings
                                                        </Link>
                                                    </Button>
                                                    <Button size="sm" variant="outline" asChild>
                                                        <Link href={route('chat.flow.edit', widget.id)}>
                                                            <Workflow className="size-3.5" aria-hidden /> Conversation
                                                        </Link>
                                                    </Button>
                                                    <Button size="sm" variant="outline" onClick={() => copyEmbed(widget)}>
                                                        {copied === widget.id ? (
                                                            <>
                                                                <Check className="size-3.5" aria-hidden /> Copied
                                                            </>
                                                        ) : copyFailed === widget.id ? (
                                                            <>Press Ctrl+C</>
                                                        ) : (
                                                            <>
                                                                <Copy className="size-3.5" aria-hidden /> Install code
                                                            </>
                                                        )}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant={widget.status === 'active' ? 'outline' : 'default'}
                                                        onClick={() => setStatus(widget, widget.status === 'active' ? 'paused' : 'active')}
                                                    >
                                                        {widget.status === 'active' ? 'Pause' : 'Publish'}
                                                    </Button>
                                                </div>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="border-border bg-muted/30 rounded-lg border p-4">
                            <h2 className="text-foreground text-sm font-semibold">Installing a widget</h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Copy the install code above and paste it just before the closing <code>&lt;/body&gt;</code> tag of your website. It
                                loads asynchronously and will not slow your pages down. Only widgets set to <strong>Active</strong> appear to
                                visitors.
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
