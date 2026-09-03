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
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calls', href: '/analytics/calls' }];

type TrackingNumber = {
    id: number;
    phone_number: string;
    label: string | null;
    source: string;
    campaign: string | null;
    is_active: boolean;
};

type Call = {
    id: number;
    from_number: string | null;
    direction: string;
    duration_seconds: number;
    status: string;
    source: string | null;
    campaign: string | null;
    score: number;
    is_qualified: boolean;
    converted: boolean;
    contact: string | null;
    occurred_at: string | null;
    has_transcript: boolean;
    summary: string | null;
};

type Direction = 'inbound' | 'outbound';
type CallStatus = 'completed' | 'missed' | 'voicemail';

const DIRECTIONS: Direction[] = ['inbound', 'outbound'];
const CALL_STATUSES: CallStatus[] = ['completed', 'missed', 'voicemail'];
const NO_NUMBER = 'none';

function formatTime(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '—';
}

function formatDuration(seconds: number): string {
    const minutes = Math.floor(seconds / 60);

    return `${minutes}m ${String(seconds % 60).padStart(2, '0')}s`;
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' {
    if (status === 'missed') return 'destructive';
    if (status === 'voicemail') return 'secondary';
    return 'default';
}

function NewNumberDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ source: string; label: string; campaign: string }>({
        source: '',
        label: '',
        campaign: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('analytics.calls.numbers.store'), {
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
                <Button>Provision number</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Provision tracking number</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="number_source">Source</Label>
                        <Input id="number_source" value={form.data.source} onChange={(e) => form.setData('source', e.target.value)} />
                        <InputError message={form.errors.source} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="number_label">Label</Label>
                            <Input id="number_label" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} />
                            <InputError message={form.errors.label} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="number_campaign">Campaign</Label>
                            <Input id="number_campaign" value={form.data.campaign} onChange={(e) => form.setData('campaign', e.target.value)} />
                            <InputError message={form.errors.campaign} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Provision
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function LogCallDialog({ numbers }: { numbers: TrackingNumber[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        call_tracking_number_id: string;
        from_number: string;
        direction: Direction;
        duration_seconds: string;
        status: CallStatus;
        converted: boolean;
    }>({
        call_tracking_number_id: NO_NUMBER,
        from_number: '',
        direction: 'inbound',
        duration_seconds: '',
        status: 'completed',
        converted: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            call_tracking_number_id: data.call_tracking_number_id === NO_NUMBER ? null : Number(data.call_tracking_number_id),
            duration_seconds: Number(data.duration_seconds),
        }));
        form.post(route('analytics.calls.store'), {
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
                <Button variant="secondary">Log call</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Log a call</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="call_number">Tracking number</Label>
                        <Select value={form.data.call_tracking_number_id} onValueChange={(v) => form.setData('call_tracking_number_id', v)}>
                            <SelectTrigger id="call_number">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_NUMBER}>Not tracked</SelectItem>
                                {numbers.map((number) => (
                                    <SelectItem key={number.id} value={String(number.id)}>
                                        {number.phone_number} ({number.source})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.call_tracking_number_id} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="call_from">From number</Label>
                            <Input id="call_from" value={form.data.from_number} onChange={(e) => form.setData('from_number', e.target.value)} />
                            <InputError message={form.errors.from_number} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="call_duration">Duration (seconds)</Label>
                            <Input
                                id="call_duration"
                                type="number"
                                min="0"
                                value={form.data.duration_seconds}
                                onChange={(e) => form.setData('duration_seconds', e.target.value)}
                            />
                            <InputError message={form.errors.duration_seconds} />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="call_direction">Direction</Label>
                            <Select value={form.data.direction} onValueChange={(v) => form.setData('direction', v as Direction)}>
                                <SelectTrigger id="call_direction">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {DIRECTIONS.map((direction) => (
                                        <SelectItem key={direction} value={direction}>
                                            {direction}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.direction} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="call_status">Status</Label>
                            <Select value={form.data.status} onValueChange={(v) => form.setData('status', v as CallStatus)}>
                                <SelectTrigger id="call_status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {CALL_STATUSES.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.status} />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.data.converted} onCheckedChange={(v) => form.setData('converted', v === true)} />
                        Converted
                    </label>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Log call
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** AISA-014: paste the real transcript so the AI summary works on what was said. */
function TranscriptDialog({ call }: { call: Call }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ transcript: string }>({ transcript: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('analytics.calls.transcript', call.id), {
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
                <Button size="sm" variant="ghost">
                    {call.has_transcript ? 'Transcript ✓' : 'Transcript'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Call transcript</DialogTitle>
                {call.summary !== null && (
                    <div className="bg-muted/50 rounded-md p-3 text-sm">
                        <p className="mb-1 font-medium">AI summary</p>
                        <p className="text-muted-foreground whitespace-pre-wrap">{call.summary}</p>
                    </div>
                )}
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`transcript_${call.id}`}>Paste the transcript</Label>
                        <textarea
                            id={`transcript_${call.id}`}
                            className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-40 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                            value={form.data.transcript}
                            onChange={(e) => form.setData('transcript', e.target.value)}
                            placeholder="Paste the call transcript or your detailed notes — the AI summary then works on what was actually said."
                        />
                        <InputError message={form.errors.transcript} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Attach transcript
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Calls({ numbers, calls, breakdown }: { numbers: TrackingNumber[]; calls: Call[]; breakdown: Record<string, number> }) {
    const { can } = usePermissions();
    const canManage = can('analytics.calls.manage');
    const sources = Object.entries(breakdown);

    const convert = (id: number) => router.post(route('analytics.calls.convert', id), {}, { preserveScroll: true });
    const summarize = (id: number) => router.post(route('analytics.calls.summarize', id), {}, { preserveScroll: true });
    const removeNumber = (id: number) => router.delete(route('analytics.calls.numbers.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calls" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Call tracking" description="Tracking numbers, logged calls and the sources that drive them" />
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <LogCallDialog numbers={numbers} />
                            <NewNumberDialog />
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Calls by source</h3>
                    {sources.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No calls yet. Sources appear here once calls are logged.</p>
                    ) : (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {sources.map(([source, total]) => (
                                <Card key={source}>
                                    <CardContent className="p-4">
                                        <p className="text-muted-foreground text-sm">{source}</p>
                                        <p className="text-2xl font-semibold">{total}</p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Tracking numbers</h3>
                    {numbers.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No tracking numbers yet. Provision one to attribute inbound calls.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Number</th>
                                        <th className="p-3 font-medium">Label</th>
                                        <th className="p-3 font-medium">Source</th>
                                        <th className="p-3 font-medium">Campaign</th>
                                        <th className="p-3 font-medium">Active</th>
                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {numbers.map((number) => (
                                        <tr key={number.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{number.phone_number}</td>
                                            <td className="text-muted-foreground p-3">{number.label ?? '—'}</td>
                                            <td className="p-3">
                                                <Badge variant="outline">{number.source}</Badge>
                                            </td>
                                            <td className="text-muted-foreground p-3">{number.campaign ?? '—'}</td>
                                            <td className="p-3">
                                                <Badge variant={number.is_active ? 'default' : 'secondary'}>
                                                    {number.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </td>
                                            {canManage && (
                                                <td className="p-3">
                                                    <div className="flex justify-end">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removeNumber(number.id)}
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

                <div>
                    <h3 className="mb-2 text-sm font-medium">Calls</h3>
                    {calls.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No calls yet. Calls appear here once they are logged.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">From</th>
                                        <th className="p-3 font-medium">Contact</th>
                                        <th className="p-3 font-medium">Direction</th>
                                        <th className="p-3 text-center font-medium">Duration</th>
                                        <th className="p-3 font-medium">Status</th>
                                        <th className="p-3 font-medium">Source</th>
                                        <th className="p-3 text-center font-medium">Score</th>
                                        <th className="p-3 font-medium">Outcome</th>
                                        <th className="p-3 font-medium">When</th>
                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {calls.map((call) => (
                                        <tr key={call.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{call.from_number ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{call.contact ?? '—'}</td>
                                            <td className="p-3">
                                                <Badge variant="outline">{call.direction}</Badge>
                                            </td>
                                            <td className="p-3 text-center">{formatDuration(call.duration_seconds)}</td>
                                            <td className="p-3">
                                                <Badge variant={statusVariant(call.status)}>{call.status}</Badge>
                                            </td>
                                            <td className="text-muted-foreground p-3">{call.source ?? '—'}</td>
                                            <td className="p-3 text-center">{call.score}</td>
                                            <td className="p-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {call.is_qualified && <Badge variant="secondary">Qualified</Badge>}
                                                    {call.converted && <Badge>Converted</Badge>}
                                                    {!call.is_qualified && !call.converted && <span className="text-muted-foreground">—</span>}
                                                </div>
                                            </td>
                                            <td className="text-muted-foreground p-3">{formatTime(call.occurred_at)}</td>
                                            {canManage && (
                                                <td className="p-3">
                                                    <div className="flex justify-end gap-1">
                                                        <TranscriptDialog call={call} />
                                                        <Button size="sm" variant="outline" onClick={() => summarize(call.id)}>
                                                            Summarize
                                                        </Button>
                                                        {!call.converted && (
                                                            <Button size="sm" variant="secondary" onClick={() => convert(call.id)}>
                                                                Mark converted
                                                            </Button>
                                                        )}
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
            </div>
        </AppLayout>
    );
}
