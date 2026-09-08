import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Retargeting', href: '/ads/retargeting' }];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-24 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function SmsDialog({ audienceId, audienceName }: { audienceId: number; audienceName: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ message: string }>({ message: '' });

    const send: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('ads.retargeting.sms', audienceId), {
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
                    SMS
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>SMS re-engagement — {audienceName}</DialogTitle>
                <form onSubmit={send} className="space-y-3">
                    <p className="text-muted-foreground text-sm">
                        Sends through the SMS engine: only opted-in, non-suppressed members with a phone number receive it, and the result reports
                        exactly what happened.
                    </p>
                    <div className="grid gap-1">
                        <Label htmlFor={`sms-${audienceId}`}>Message</Label>
                        <textarea
                            id={`sms-${audienceId}`}
                            className={textareaClass}
                            maxLength={480}
                            value={form.data.message}
                            onChange={(e) => form.setData('message', e.target.value)}
                        />
                        <InputError message={form.errors.message} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || form.data.message.trim() === ''}>
                            Send to audience
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type Audience = {
    id: number;
    name: string;
    source: string;
    list: string | null;
    platforms: string[];
    exclude_converted: boolean;
    member_count: number;
};

type ListOption = { id: number; name: string };

type Source = 'list' | 'behavior' | 'funnel_stage' | 'all_contacts';

const SOURCES: Source[] = ['list', 'behavior', 'funnel_stage', 'all_contacts'];
const PLATFORM_OPTIONS = ['google', 'meta', 'linkedin', 'youtube'];

function VideoCampaignDialog({ audienceId, audienceName, videoPieces }: { audienceId: number; audienceName: string; videoPieces: ListOption[] }) {
    const [open, setOpen] = useState(false);
    const [pieceId, setPieceId] = useState('');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (pieceId === '') return;
        router.post(route('ads.retargeting.video', audienceId), { content_piece_id: Number(pieceId) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    YouTube campaign
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Video retargeting — {audienceName}</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    Builds a draft YouTube video campaign from the chosen piece with this audience attached (Customer Match export ready). Live
                    delivery goes out through Google Ads when the connector is wired.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="video-piece">Video content</Label>
                        <Select value={pieceId} onValueChange={setPieceId}>
                            <SelectTrigger id="video-piece">
                                <SelectValue placeholder="Pick a video / webinar / podcast piece" />
                            </SelectTrigger>
                            <SelectContent>
                                {videoPieces.map((piece) => (
                                    <SelectItem key={piece.id} value={String(piece.id)}>
                                        {piece.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={pieceId === ''}>
                            Create draft campaign
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Retargeting({
    audiences,
    lists,
    video_pieces,
}: {
    audiences: Audience[];
    lists: ListOption[];
    video_pieces: { id: number; title: string }[];
}) {
    const { can } = usePermissions();
    const canManage = can('ads.retargeting.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{
        name: string;
        source: Source;
        marketing_list_id: string;
        lifecycle_stage: string;
        min_lead_score: string;
        platforms: string[];
        exclude_converted: boolean;
    }>({
        name: '',
        source: 'list',
        marketing_list_id: '',
        lifecycle_stage: '',
        min_lead_score: '',
        platforms: [],
        exclude_converted: true,
    });

    const togglePlatform = (platform: string, checked: boolean) => {
        form.setData('platforms', checked ? [...form.data.platforms, platform] : form.data.platforms.filter((p) => p !== platform));
    };

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => {
            const body: Record<string, unknown> = {
                name: data.name,
                source: data.source,
                platforms: data.platforms,
                exclude_converted: data.exclude_converted,
            };
            if (data.source === 'list' && data.marketing_list_id) {
                body.marketing_list_id = data.marketing_list_id;
            }
            if (data.source === 'funnel_stage') {
                body.rules = { lifecycle_stage: data.lifecycle_stage };
            }
            if (data.source === 'behavior') {
                body.rules = { min_lead_score: data.min_lead_score ? Number(data.min_lead_score) : null };
            }
            return body;
        });
        form.post(route('ads.retargeting.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    const rebuild = (id: number) => router.post(route('ads.retargeting.rebuild', id), {}, { preserveScroll: true });
    const destroy = (id: number) => router.delete(route('ads.retargeting.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Retargeting" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Retargeting" description={`${audiences.length} audiences`} />
                    {canManage && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>New audience</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>New audience</DialogTitle>
                                <form onSubmit={create} className="space-y-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="name">Name</Label>
                                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                        <InputError message={form.errors.name} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="source">Source</Label>
                                        <Select value={form.data.source} onValueChange={(v) => form.setData('source', v as Source)}>
                                            <SelectTrigger id="source">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {SOURCES.map((source) => (
                                                    <SelectItem key={source} value={source}>
                                                        {source.replace('_', ' ')}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {form.data.source === 'list' && (
                                        <div className="grid gap-1">
                                            <Label htmlFor="marketing_list_id">List</Label>
                                            <Select value={form.data.marketing_list_id} onValueChange={(v) => form.setData('marketing_list_id', v)}>
                                                <SelectTrigger id="marketing_list_id">
                                                    <SelectValue placeholder="Select a list" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {lists.map((list) => (
                                                        <SelectItem key={list.id} value={String(list.id)}>
                                                            {list.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={form.errors.marketing_list_id} />
                                        </div>
                                    )}

                                    {form.data.source === 'funnel_stage' && (
                                        <div className="grid gap-1">
                                            <Label htmlFor="lifecycle_stage">Lifecycle stage</Label>
                                            <Input
                                                id="lifecycle_stage"
                                                value={form.data.lifecycle_stage}
                                                onChange={(e) => form.setData('lifecycle_stage', e.target.value)}
                                            />
                                        </div>
                                    )}

                                    {form.data.source === 'behavior' && (
                                        <div className="grid gap-1">
                                            <Label htmlFor="min_lead_score">Minimum lead score</Label>
                                            <Input
                                                id="min_lead_score"
                                                type="number"
                                                min="0"
                                                value={form.data.min_lead_score}
                                                onChange={(e) => form.setData('min_lead_score', e.target.value)}
                                            />
                                        </div>
                                    )}

                                    <div className="grid gap-1">
                                        <Label>Platforms</Label>
                                        <div className="grid grid-cols-2 gap-2">
                                            {PLATFORM_OPTIONS.map((platform) => (
                                                <label key={platform} className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={form.data.platforms.includes(platform)}
                                                        onCheckedChange={(v) => togglePlatform(platform, v === true)}
                                                    />
                                                    {platform}
                                                </label>
                                            ))}
                                        </div>
                                        <InputError message={form.errors.platforms} />
                                    </div>

                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={form.data.exclude_converted}
                                            onCheckedChange={(v) => form.setData('exclude_converted', v === true)}
                                        />
                                        Exclude converted contacts
                                    </label>

                                    <DialogFooter>
                                        <Button type="submit" disabled={form.processing}>
                                            Create
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {audiences.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No audiences yet. Create an audience to retarget your contacts.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Name</th>
                                    <th className="p-3 font-medium">Source</th>
                                    <th className="p-3 font-medium">List</th>
                                    <th className="p-3 font-medium">Platforms</th>
                                    <th className="p-3 text-center font-medium">Members</th>
                                    <th className="p-3 text-center font-medium">Exclude converted</th>
                                    {canManage && <th className="p-3 font-medium">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {audiences.map((audience) => (
                                    <tr key={audience.id} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{audience.name}</td>
                                        <td className="p-3">
                                            <Badge variant="outline">{audience.source}</Badge>
                                        </td>
                                        <td className="text-muted-foreground p-3">{audience.list ?? '—'}</td>
                                        <td className="text-muted-foreground p-3">{audience.platforms.join(', ') || '—'}</td>
                                        <td className="p-3 text-center">{audience.member_count}</td>
                                        <td className="p-3 text-center">{audience.exclude_converted ? 'Yes' : 'No'}</td>
                                        {canManage && (
                                            <td className="p-3">
                                                <div className="flex flex-wrap gap-2">
                                                    <Button size="sm" variant="secondary" onClick={() => rebuild(audience.id)}>
                                                        Rebuild
                                                    </Button>
                                                    {(['google', 'meta', 'linkedin'] as const).map((platform) => (
                                                        <Button key={platform} size="sm" variant="outline" asChild>
                                                            <a href={route('ads.retargeting.export', audience.id) + `?platform=${platform}`}>
                                                                {platform === 'google'
                                                                    ? 'Google CSV'
                                                                    : platform === 'meta'
                                                                      ? 'Meta CSV'
                                                                      : 'LinkedIn CSV'}
                                                            </a>
                                                        </Button>
                                                    ))}
                                                    <SmsDialog audienceId={audience.id} audienceName={audience.name} />
                                                    {video_pieces.length > 0 && (
                                                        <VideoCampaignDialog
                                                            audienceId={audience.id}
                                                            audienceName={audience.name}
                                                            videoPieces={video_pieces.map((p) => ({ id: p.id, name: p.title }))}
                                                        />
                                                    )}
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive"
                                                        onClick={() => destroy(audience.id)}
                                                    >
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
            </div>
        </AppLayout>
    );
}
