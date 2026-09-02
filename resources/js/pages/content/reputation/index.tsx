import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reputation', href: '/content/reputation' }];

type Aggregate = {
    count: number;
    average: number;
    by_sentiment: { positive: number; neutral: number; negative: number };
    by_source: Record<string, number>;
};

type DirectoryChecklist = {
    id: number;
    directory: string | null;
    name: string;
    ok: boolean;
    checks: { key: string; label: string; ok: boolean; detail: string }[];
};

type Review = {
    id: number;
    source: string;
    author_name: string | null;
    rating: number;
    body: string | null;
    video_url: string | null;
    sentiment: string;
    responded: boolean;
    response: string | null;
};

type ReviewRequest = {
    id: number;
    name: string;
    channel: string;
    status: string;
};

type AuthorityAsset = {
    id: number;
    type: string;
    name: string;
    issuer: string | null;
    url: string | null;
};

const REVIEW_SOURCES = ['google', 'clutch', 'g2', 'facebook', 'manual'];
const IMPORT_SOURCES = ['google', 'clutch'];
const RATINGS = ['1', '2', '3', '4', '5'];
const REQUEST_CHANNELS = ['email', 'sms'];
const ASSET_TYPES = [
    'award',
    'certification',
    'logo',
    'mention',
    'proof',
    'video_testimonial',
    'directory_profile',
    'article',
    'press',
    'expert_quote',
    'thought_leadership',
    'backlink',
];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function sentimentVariant(sentiment: string): 'default' | 'secondary' | 'destructive' {
    if (sentiment === 'positive') return 'default';
    if (sentiment === 'negative') return 'destructive';
    return 'secondary';
}

function RecordReviewDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ source: string; author_name: string; rating: string; body: string; url: string; video_url: string }>({
        source: 'google',
        author_name: '',
        rating: '5',
        body: '',
        url: '',
        video_url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, rating: Number(data.rating) }));
        form.post(route('content.reputation.reviews.store'), {
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
                <Button>Record review</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Record review</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="review_source">Source</Label>
                            <Select value={form.data.source} onValueChange={(v) => form.setData('source', v)}>
                                <SelectTrigger id="review_source">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {REVIEW_SOURCES.map((source) => (
                                        <SelectItem key={source} value={source}>
                                            {source}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="review_rating">Rating</Label>
                            <Select value={form.data.rating} onValueChange={(v) => form.setData('rating', v)}>
                                <SelectTrigger id="review_rating">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {RATINGS.map((rating) => (
                                        <SelectItem key={rating} value={rating}>
                                            {rating}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="review_author">Author</Label>
                        <Input id="review_author" value={form.data.author_name} onChange={(e) => form.setData('author_name', e.target.value)} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="review_body">Body</Label>
                        <textarea
                            id="review_body"
                            className={textareaClass}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="review_url">URL</Label>
                        <Input id="review_url" type="url" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} />
                        <InputError message={form.errors.url} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="review_video">Video testimonial URL (optional)</Label>
                        <Input id="review_video" type="url" value={form.data.video_url} onChange={(e) => form.setData('video_url', e.target.value)} />
                        <InputError message={form.errors.video_url} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Record
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ImportReviewsDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ source: string; identifier: string }>({ source: 'google', identifier: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('content.reputation.import'), {
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
                <Button variant="secondary">Import reviews</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Import reviews</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="import_source">Source</Label>
                        <Select value={form.data.source} onValueChange={(v) => form.setData('source', v)}>
                            <SelectTrigger id="import_source">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {IMPORT_SOURCES.map((source) => (
                                    <SelectItem key={source} value={source}>
                                        {source}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="import_identifier">Identifier</Label>
                        <Input
                            id="import_identifier"
                            value={form.data.identifier}
                            onChange={(e) => form.setData('identifier', e.target.value)}
                            placeholder="Place ID or profile handle"
                        />
                        <InputError message={form.errors.identifier} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Import
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RespondDialog({ review }: { review: Review }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ response: string }>({ response: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('content.reputation.reviews.respond', review.id), {
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
                    Respond
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Respond to review</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`response_${review.id}`}>Response</Label>
                        <textarea
                            id={`response_${review.id}`}
                            className={textareaClass}
                            value={form.data.response}
                            onChange={(e) => form.setData('response', e.target.value)}
                        />
                        <InputError message={form.errors.response} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Send response
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NewRequestDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; channel: string }>({ name: '', channel: 'email' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('content.reputation.requests.store'), {
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
                <Button size="sm">New request</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New review request</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="request_name">Name</Label>
                        <Input id="request_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="request_channel">Channel</Label>
                        <Select value={form.data.channel} onValueChange={(v) => form.setData('channel', v)}>
                            <SelectTrigger id="request_channel">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {REQUEST_CHANNELS.map((channel) => (
                                    <SelectItem key={channel} value={channel}>
                                        {channel}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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

function AddAssetDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ type: string; name: string; issuer: string; url: string }>({
        type: 'award',
        name: '',
        issuer: '',
        url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('content.reputation.assets.store'), {
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
                <Button size="sm">Add asset</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add authority asset</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="asset_type">Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger id="asset_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ASSET_TYPES.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="asset_name">Name</Label>
                            <Input id="asset_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            <InputError message={form.errors.name} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_issuer">Issuer</Label>
                        <Input id="asset_issuer" value={form.data.issuer} onChange={(e) => form.setData('issuer', e.target.value)} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_url">URL</Label>
                        <Input id="asset_url" type="url" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} />
                        <InputError message={form.errors.url} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Reputation({
    aggregate,
    reviews,
    requests,
    assets,
    directoryChecklists,
}: {
    aggregate: Aggregate;
    reviews: Review[];
    requests: ReviewRequest[];
    assets: AuthorityAsset[];
    directoryChecklists?: DirectoryChecklist[];
}) {
    const { can } = usePermissions();
    const canManage = can('content.reputation.manage');

    const sendRequest = (id: number) => router.post(route('content.reputation.requests.send', id), {}, { preserveScroll: true });
    const removeAsset = (id: number) => router.delete(route('content.reputation.assets.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reputation" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Reputation" description="Reviews, requests, and authority signals" />
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <RecordReviewDialog />
                            <ImportReviewsDialog />
                            <Button
                                variant="outline"
                                onClick={() => router.post(route('content.reputation.proof-page'), {}, { preserveScroll: true })}
                            >
                                Draft proof page
                            </Button>
                        </div>
                    )}
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-center justify-between gap-6 p-4">
                        <div>
                            <p className="text-3xl font-bold">{aggregate.average} ★</p>
                            <p className="text-muted-foreground text-xs">{aggregate.count} reviews</p>
                        </div>
                        <div className="flex gap-6 text-sm">
                            <div>
                                <p className="text-muted-foreground text-xs">Positive</p>
                                <p className="text-lg font-semibold">{aggregate.by_sentiment.positive}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs">Neutral</p>
                                <p className="text-lg font-semibold">{aggregate.by_sentiment.neutral}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs">Negative</p>
                                <p className="text-lg font-semibold">{aggregate.by_sentiment.negative}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Reviews</h3>
                    {reviews.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No reviews yet. Record or import a review to start tracking sentiment.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Source</th>
                                        <th className="p-3 font-medium">Author</th>
                                        <th className="p-3 text-center font-medium">Rating</th>
                                        <th className="p-3 font-medium">Sentiment</th>
                                        <th className="p-3 font-medium">Body</th>
                                        <th className="p-3 text-right font-medium">Response</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {reviews.map((review) => (
                                        <tr key={review.id} className="hover:bg-muted/40">
                                            <td className="p-3">
                                                <Badge variant="outline">{review.source}</Badge>
                                            </td>
                                            <td className="text-muted-foreground p-3">{review.author_name ?? '—'}</td>
                                            <td className="p-3 text-center">{review.rating}★</td>
                                            <td className="p-3">
                                                <Badge variant={sentimentVariant(review.sentiment)}>{review.sentiment}</Badge>
                                            </td>
                                            <td className="p-3">
                                                <p className="max-w-xs truncate">{review.body ?? '—'}</p>
                                                {review.video_url && (
                                                    <a
                                                        href={review.video_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="text-primary text-xs underline"
                                                    >
                                                        Video testimonial
                                                    </a>
                                                )}
                                            </td>
                                            <td className="p-3 text-right">
                                                {review.responded ? (
                                                    <span className="text-muted-foreground text-xs">Responded</span>
                                                ) : canManage ? (
                                                    <RespondDialog review={review} />
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Review requests</h3>
                        {canManage && <NewRequestDialog />}
                    </div>
                    {requests.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No requests yet. Create a request to ask a customer for a review.</p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {requests.map((request) => (
                                <div key={request.id} className="flex items-center justify-between gap-3 p-3">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium">{request.name}</span>
                                        <Badge variant="outline">{request.channel}</Badge>
                                        <Badge variant={request.status === 'sent' ? 'default' : 'secondary'}>{request.status}</Badge>
                                    </div>
                                    {canManage && request.status === 'pending' && (
                                        <Button size="sm" variant="secondary" onClick={() => sendRequest(request.id)}>
                                            Send
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Authority assets</h3>
                        {canManage && <AddAssetDialog />}
                    </div>
                    {assets.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No assets yet. Add awards, certifications, or proof to build authority.</p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {assets.map((asset) => (
                                <div key={asset.id} className="flex items-center justify-between gap-3 p-3">
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">{asset.type}</Badge>
                                        <span className="text-sm font-medium">{asset.name}</span>
                                        {asset.issuer && <span className="text-muted-foreground text-xs">{asset.issuer}</span>}
                                    </div>
                                    {canManage && (
                                        <Button size="sm" variant="ghost" className="text-destructive" onClick={() => removeAsset(asset.id)}>
                                            Delete
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {(directoryChecklists ?? []).length > 0 && (
                    <div>
                        <h3 className="mb-2 text-sm font-medium">Directory profile optimization</h3>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {(directoryChecklists ?? []).map((profile) => (
                                <Card key={profile.id} className="min-w-0">
                                    <CardContent className="p-4">
                                        <div className="mb-2 flex flex-wrap items-center gap-2">
                                            <h4 className="text-sm font-medium">{profile.directory ?? profile.name}</h4>
                                            <Badge variant={profile.ok ? 'default' : 'secondary'}>{profile.ok ? 'Optimized' : 'Needs work'}</Badge>
                                        </div>
                                        <ul className="space-y-1 text-sm">
                                            {profile.checks.map((check) => (
                                                <li key={check.key} className="text-muted-foreground">
                                                    <span className={check.ok ? 'text-green-600' : 'text-destructive'}>{check.ok ? '✓' : '✗'}</span>{' '}
                                                    <span className="text-foreground font-medium">{check.label}</span> — {check.detail}
                                                </li>
                                            ))}
                                        </ul>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
