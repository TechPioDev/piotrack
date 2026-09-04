import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Social', href: '/content/social' }];

type Post = {
    id: number;
    channel: string;
    type: string | null;
    body: string;
    status: string;
    scheduled_at: string | null;
    published_at: string | null;
    impressions: number;
    likes: number;
    comments: number;
    shares: number;
};

type PieceOption = { id: number; title: string };

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' {
    if (status === 'published') return 'default';
    if (status === 'failed') return 'destructive';
    return 'secondary';
}

function NewPostDialog({ channels, pieces }: { channels: string[]; pieces: PieceOption[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ channel: string; type: string; body: string; media_url: string; content_piece_id: string }>({
        channel: channels[0] ?? '',
        type: '',
        body: '',
        media_url: '',
        content_piece_id: '',
    });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('content.social.store'), {
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
                <Button>New post</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New post</DialogTitle>
                <form onSubmit={create} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="channel">Channel</Label>
                            <Select value={form.data.channel} onValueChange={(v) => form.setData('channel', v)}>
                                <SelectTrigger id="channel">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {channels.map((channel) => (
                                        <SelectItem key={channel} value={channel}>
                                            {channel}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.channel} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="type">Type</Label>
                            <Input id="type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="body">Body</Label>
                        <textarea id="body" className={textareaClass} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                        <InputError message={form.errors.body} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="media_url">Media URL</Label>
                        <Input id="media_url" type="url" value={form.data.media_url} onChange={(e) => form.setData('media_url', e.target.value)} />
                        <InputError message={form.errors.media_url} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="content_piece_id">Content piece</Label>
                        <Select value={form.data.content_piece_id} onValueChange={(v) => form.setData('content_piece_id', v)}>
                            <SelectTrigger id="content_piece_id">
                                <SelectValue placeholder="Attach content (optional)" />
                            </SelectTrigger>
                            <SelectContent>
                                {pieces.map((piece) => (
                                    <SelectItem key={piece.id} value={String(piece.id)}>
                                        {piece.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.content_piece_id} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ScheduleDialog({ post }: { post: Post }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ scheduled_at: string }>({ scheduled_at: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('content.social.schedule', post.id), {
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
                <Button size="sm" variant="secondary">
                    Schedule
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Schedule post</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`scheduled_at_${post.id}`}>Publish at</Label>
                        <Input
                            id={`scheduled_at_${post.id}`}
                            type="datetime-local"
                            value={form.data.scheduled_at}
                            onChange={(e) => form.setData('scheduled_at', e.target.value)}
                        />
                        <InputError message={form.errors.scheduled_at} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Schedule
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type StrategyNetwork = {
    network: string;
    published_30d: number;
    per_week: number;
    scheduled_ahead: number;
    last_published_at: string | null;
};

type Strategy = {
    networks: StrategyNetwork[];
    mix: Record<string, number>;
    recommendations: { evidence: string; action: string }[];
};

type AttributionRow = {
    network: string;
    visitors: number;
    leads: number;
    customers: number;
    won_revenue: number;
};

export default function SocialPosts({
    posts,
    channels,
    pieces,
    strategy,
    attribution,
}: {
    posts: Post[];
    channels: string[];
    pieces: PieceOption[];
    strategy: Strategy;
    attribution: AttributionRow[];
}) {
    const { can } = usePermissions();
    const canManage = can('content.social.manage');

    const publish = (id: number) => router.post(route('content.social.publish', id), {}, { preserveScroll: true });
    const refresh = (id: number) => router.post(route('content.social.refresh-metrics', id), {}, { preserveScroll: true });
    const sponsor = (id: number) => router.post(route('content.social.sponsor', id), {}, { preserveScroll: true });
    const remove = (id: number) => router.delete(route('content.social.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Social" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Social" description={`${posts.length} total`} />
                    {canManage && <NewPostDialog channels={channels} pieces={pieces} />}
                </div>

                {posts.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No posts yet. Create a post to plan your social calendar.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Channel</th>
                                    <th className="p-3 font-medium">Type</th>
                                    <th className="p-3 font-medium">Body</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 text-center font-medium">Engagement</th>
                                    {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {posts.map((post) => (
                                    <tr key={post.id} className="hover:bg-muted/40">
                                        <td className="p-3">
                                            <Badge variant="outline">{post.channel}</Badge>
                                        </td>
                                        <td className="text-muted-foreground p-3">{post.type ?? '—'}</td>
                                        <td className="p-3">
                                            <p className="max-w-xs truncate">{post.body}</p>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant={statusVariant(post.status)}>{post.status}</Badge>
                                        </td>
                                        <td className="text-muted-foreground p-3 text-center text-xs">
                                            {post.impressions} impr · {post.likes} likes · {post.comments} comments · {post.shares} shares
                                        </td>
                                        {canManage && (
                                            <td className="p-3">
                                                <div className="flex flex-wrap justify-end gap-2">
                                                    <ScheduleDialog post={post} />
                                                    <Button size="sm" onClick={() => publish(post.id)}>
                                                        Publish
                                                    </Button>
                                                    <Button size="sm" variant="secondary" onClick={() => refresh(post.id)}>
                                                        Refresh
                                                    </Button>
                                                    <Button size="sm" variant="outline" onClick={() => sponsor(post.id)}>
                                                        Sponsor
                                                    </Button>
                                                    <Button size="sm" variant="ghost" className="text-destructive" onClick={() => remove(post.id)}>
                                                        Delete
                                                    </Button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 className="mb-1 text-sm font-medium">Strategy</h3>
                        <p className="text-muted-foreground mb-2 text-xs">
                            Cadence and mix from your own posting records over the last 30 days — recommendations only when a number warrants one.
                        </p>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Network</th>
                                        <th className="p-3 text-center font-medium">30d posts</th>
                                        <th className="p-3 text-center font-medium">Per week</th>
                                        <th className="p-3 text-center font-medium">Scheduled</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {strategy.networks.map((row) => (
                                        <tr key={row.network}>
                                            <td className="p-3 font-medium">{row.network}</td>
                                            <td className="p-3 text-center">{row.published_30d}</td>
                                            <td className="p-3 text-center">{row.per_week}</td>
                                            <td className="p-3 text-center">{row.scheduled_ahead}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {strategy.recommendations.length > 0 && (
                            <ul className="mt-2 space-y-2 rounded-lg border p-3 text-sm">
                                {strategy.recommendations.map((rec, index) => (
                                    <li key={index}>
                                        <p className="font-medium">{rec.action}</p>
                                        <p className="text-muted-foreground text-xs">{rec.evidence}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div>
                        <h3 className="mb-1 text-sm font-medium">Lead attribution</h3>
                        <p className="text-muted-foreground mb-2 text-xs">
                            Visitors and leads whose first touch the attribution engine classified as social, by network.
                        </p>
                        {attribution.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No social-attributed visitors or leads yet — use utm_source on your post links so the pixel can attribute them.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Network</th>
                                            <th className="p-3 text-center font-medium">Visitors</th>
                                            <th className="p-3 text-center font-medium">Leads</th>
                                            <th className="p-3 text-center font-medium">Customers</th>
                                            <th className="p-3 text-right font-medium">Won revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {attribution.map((row) => (
                                            <tr key={row.network}>
                                                <td className="p-3 font-medium">{row.network}</td>
                                                <td className="p-3 text-center">{row.visitors}</td>
                                                <td className="p-3 text-center">{row.leads}</td>
                                                <td className="p-3 text-center">{row.customers}</td>
                                                <td className="p-3 text-right">${(row.won_revenue / 100).toLocaleString('en-US')}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
