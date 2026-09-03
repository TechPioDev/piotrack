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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Brand', href: '/strategy/brand' }];

type KeyedList = Record<string, string> | string[] | null;

type Profile = {
    id: number;
    positioning_statement: string | null;
    usp: string | null;
    value_proposition: string | null;
    differentiators: string[] | null;
    narrative: string | null;
    story: string | null;
    tone_of_voice: string | null;
    messaging_hierarchy: string[] | null;
    elevator_pitch: string | null;
    tagline: string | null;
    palette: KeyedList;
    typography: KeyedList;
    imagery_direction: string | null;
    guidelines_url: string | null;
} | null;

type Asset = {
    id: number;
    type: string;
    title: string;
    url: string | null;
    notes: string | null;
};

type Engagement = {
    id: number;
    type: string;
    topic: string | null;
    title: string;
    status: string;
    attendees: number | null;
    notes: string | null;
    scheduled_at: string | null;
};

const engagementStatuses = ['scheduled', 'completed', 'canceled'];

type Positioning = {
    discovery: { answered: number; total: number; items: { key: string; label: string; ok: boolean; detail: string }[] };
    competitorMessaging: { competitor: string; checked_at: string | null; titles: string[] }[];
    differentiators: { differentiator: string; on_our_site: boolean; claimed_by: string[] }[];
    icpAlignment: { insufficient_data: boolean; checks: { attribute: string; stated: string; observed: string; aligned: boolean }[] };
    evidence: {
        premium: { won_deals: number; avg_deal_value: number | null; median_mrr: number | null };
        vertical: { active: number; with_published_page: number };
        service: { active: number; with_published_page: number };
        geographic: { branches: number; with_published_page: number };
    };
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

const humanize = (value: string) => value.replace(/_/g, ' ');

function formatTime(iso: string | null): string {
    return iso === null ? '—' : new Date(iso).toLocaleString();
}

/** `a, b, c` <-> `['a', 'b', 'c']`. */
function listToText(list: string[] | null): string {
    return (list ?? []).join(', ');
}

function textToList(value: string): string[] {
    return value
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part !== '');
}

/** `key: value` per line <-> `{ key: value }`, tolerating a plain list too. */
function keyedToText(value: KeyedList): string {
    if (value === null) return '';
    if (Array.isArray(value)) return value.join('\n');

    return Object.entries(value)
        .map(([key, entry]) => `${key}: ${entry}`)
        .join('\n');
}

function textToKeyed(value: string): Record<string, string> {
    const result: Record<string, string> = {};

    value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '')
        .forEach((line) => {
            const separator = line.indexOf(':');
            const key = separator === -1 ? line : line.slice(0, separator).trim();
            const entry = separator === -1 ? '' : line.slice(separator + 1).trim();

            if (key !== '') {
                result[key] = entry;
            }
        });

    return result;
}

function BrandProfileForm({ profile, canManage }: { profile: Profile; canManage: boolean }) {
    const form = useForm({
        positioning_statement: profile?.positioning_statement ?? '',
        usp: profile?.usp ?? '',
        value_proposition: profile?.value_proposition ?? '',
        differentiators: listToText(profile?.differentiators ?? null),
        narrative: profile?.narrative ?? '',
        story: profile?.story ?? '',
        tone_of_voice: profile?.tone_of_voice ?? '',
        messaging_hierarchy: listToText(profile?.messaging_hierarchy ?? null),
        elevator_pitch: profile?.elevator_pitch ?? '',
        tagline: profile?.tagline ?? '',
        palette: keyedToText(profile?.palette ?? null),
        typography: keyedToText(profile?.typography ?? null),
        imagery_direction: profile?.imagery_direction ?? '',
        guidelines_url: profile?.guidelines_url ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            differentiators: textToList(data.differentiators),
            messaging_hierarchy: textToList(data.messaging_hierarchy),
            palette: textToKeyed(data.palette),
            typography: textToKeyed(data.typography),
            guidelines_url: data.guidelines_url === '' ? null : data.guidelines_url,
        }));
        form.post(route('strategy.brand.save'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-4 rounded-lg border p-4">
            <div className="grid gap-4 lg:grid-cols-2">
                <div className="grid gap-1">
                    <Label htmlFor="brand_positioning">Positioning statement</Label>
                    <textarea
                        id="brand_positioning"
                        className={textareaClass}
                        disabled={!canManage}
                        value={form.data.positioning_statement}
                        onChange={(e) => form.setData('positioning_statement', e.target.value)}
                    />
                    <InputError message={form.errors.positioning_statement} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_usp">USP</Label>
                    <textarea
                        id="brand_usp"
                        className={textareaClass}
                        disabled={!canManage}
                        value={form.data.usp}
                        onChange={(e) => form.setData('usp', e.target.value)}
                    />
                    <InputError message={form.errors.usp} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_value">Value proposition</Label>
                    <textarea
                        id="brand_value"
                        className={textareaClass}
                        disabled={!canManage}
                        value={form.data.value_proposition}
                        onChange={(e) => form.setData('value_proposition', e.target.value)}
                    />
                    <InputError message={form.errors.value_proposition} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_narrative">Narrative</Label>
                    <textarea
                        id="brand_narrative"
                        className={textareaClass}
                        disabled={!canManage}
                        value={form.data.narrative}
                        onChange={(e) => form.setData('narrative', e.target.value)}
                    />
                    <InputError message={form.errors.narrative} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_story">Story</Label>
                    <textarea
                        id="brand_story"
                        className={textareaClass}
                        disabled={!canManage}
                        value={form.data.story}
                        onChange={(e) => form.setData('story', e.target.value)}
                    />
                    <InputError message={form.errors.story} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_pitch">Elevator pitch</Label>
                    <textarea
                        id="brand_pitch"
                        className={textareaClass}
                        disabled={!canManage}
                        value={form.data.elevator_pitch}
                        onChange={(e) => form.setData('elevator_pitch', e.target.value)}
                    />
                    <InputError message={form.errors.elevator_pitch} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_imagery">Imagery direction</Label>
                    <textarea
                        id="brand_imagery"
                        className={textareaClass}
                        disabled={!canManage}
                        value={form.data.imagery_direction}
                        onChange={(e) => form.setData('imagery_direction', e.target.value)}
                    />
                    <InputError message={form.errors.imagery_direction} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_differentiators">Differentiators</Label>
                    <Input
                        id="brand_differentiators"
                        disabled={!canManage}
                        value={form.data.differentiators}
                        onChange={(e) => form.setData('differentiators', e.target.value)}
                        placeholder="Comma separated"
                    />
                    <p className="text-muted-foreground text-xs">Comma separated, e.g. “Vertical focus, In-house delivery, Fixed-fee pricing”.</p>
                    <InputError message={form.errors.differentiators} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_hierarchy">Messaging hierarchy</Label>
                    <Input
                        id="brand_hierarchy"
                        disabled={!canManage}
                        value={form.data.messaging_hierarchy}
                        onChange={(e) => form.setData('messaging_hierarchy', e.target.value)}
                        placeholder="Comma separated, most important first"
                    />
                    <p className="text-muted-foreground text-xs">Comma separated, ordered from the primary message down.</p>
                    <InputError message={form.errors.messaging_hierarchy} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_tone">Tone of voice</Label>
                    <Input
                        id="brand_tone"
                        disabled={!canManage}
                        value={form.data.tone_of_voice}
                        onChange={(e) => form.setData('tone_of_voice', e.target.value)}
                    />
                    <InputError message={form.errors.tone_of_voice} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_tagline">Tagline</Label>
                    <Input
                        id="brand_tagline"
                        disabled={!canManage}
                        value={form.data.tagline}
                        onChange={(e) => form.setData('tagline', e.target.value)}
                    />
                    <InputError message={form.errors.tagline} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_palette">Palette</Label>
                    <textarea
                        id="brand_palette"
                        className={textareaClass}
                        disabled={!canManage}
                        value={form.data.palette}
                        onChange={(e) => form.setData('palette', e.target.value)}
                        placeholder={'primary: #0F172A\naccent: #22C55E'}
                    />
                    <p className="text-muted-foreground text-xs">One “name: value” per line.</p>
                    <InputError message={form.errors.palette} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_typography">Typography</Label>
                    <textarea
                        id="brand_typography"
                        className={textareaClass}
                        disabled={!canManage}
                        value={form.data.typography}
                        onChange={(e) => form.setData('typography', e.target.value)}
                        placeholder={'heading: Inter Semibold\nbody: Inter Regular'}
                    />
                    <p className="text-muted-foreground text-xs">One “name: value” per line.</p>
                    <InputError message={form.errors.typography} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="brand_guidelines">Guidelines URL</Label>
                    <Input
                        id="brand_guidelines"
                        type="url"
                        disabled={!canManage}
                        value={form.data.guidelines_url}
                        onChange={(e) => form.setData('guidelines_url', e.target.value)}
                        placeholder="https://"
                    />
                    <InputError message={form.errors.guidelines_url} />
                </div>
            </div>
            {canManage ? (
                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        Save brand profile
                    </Button>
                </div>
            ) : (
                <p className="text-muted-foreground text-sm">
                    You can read the brand profile but not change it. Editing requires the <code>strategy.manage</code> permission.
                </p>
            )}
        </form>
    );
}

function NewAssetDialog({ assetTypes }: { assetTypes: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ type: string; title: string; url: string; notes: string }>({
        type: assetTypes[0] ?? 'logo',
        title: '',
        url: '',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, url: data.url === '' ? null : data.url }));
        form.post(route('strategy.brand.assets.store'), {
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
                    Add asset
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add a brand asset</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="asset_type">Type</Label>
                        <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                            <SelectTrigger id="asset_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {assetTypes.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {humanize(type)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.type} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_title">Title</Label>
                        <Input id="asset_title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_url">URL</Label>
                        <Input
                            id="asset_url"
                            type="url"
                            value={form.data.url}
                            onChange={(e) => form.setData('url', e.target.value)}
                            placeholder="https://"
                        />
                        <InputError message={form.errors.url} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_notes">Notes</Label>
                        <textarea
                            id="asset_notes"
                            className={textareaClass}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                        <InputError message={form.errors.notes} />
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

function NewEngagementDialog({ engagementTypes, engagementTopics }: { engagementTypes: string[]; engagementTopics: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ type: string; topic: string; title: string; scheduled_at: string; attendees: string; notes: string }>({
        type: engagementTypes[0] ?? 'training',
        topic: '',
        title: '',
        scheduled_at: '',
        attendees: '',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            topic: data.topic === '' ? null : data.topic,
            attendees: data.attendees === '' ? null : Number(data.attendees),
            scheduled_at: data.scheduled_at === '' ? null : data.scheduled_at,
        }));
        form.post(route('strategy.engagements.store'), {
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
                    Schedule engagement
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Schedule a training or consulting engagement</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="engagement_type">Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger id="engagement_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {engagementTypes.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {humanize(type)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="engagement_topic">Topic</Label>
                            <Select value={form.data.topic} onValueChange={(v) => form.setData('topic', v)}>
                                <SelectTrigger id="engagement_topic">
                                    <SelectValue placeholder="No topic" />
                                </SelectTrigger>
                                <SelectContent>
                                    {engagementTopics.map((topic) => (
                                        <SelectItem key={topic} value={topic}>
                                            {topic}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.topic} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="engagement_title">Title</Label>
                        <Input id="engagement_title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="engagement_when">Scheduled for</Label>
                            <Input
                                id="engagement_when"
                                type="datetime-local"
                                value={form.data.scheduled_at}
                                onChange={(e) => form.setData('scheduled_at', e.target.value)}
                            />
                            <InputError message={form.errors.scheduled_at} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="engagement_attendees">Attendees</Label>
                            <Input
                                id="engagement_attendees"
                                type="number"
                                min="0"
                                value={form.data.attendees}
                                onChange={(e) => form.setData('attendees', e.target.value)}
                            />
                            <InputError message={form.errors.attendees} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="engagement_notes">Notes</Label>
                        <textarea
                            id="engagement_notes"
                            className={textareaClass}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                        <InputError message={form.errors.notes} />
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

export default function StrategyBrand({
    profile,
    positioning,
    assets,
    asset_types,
    engagements,
    engagement_types,
    engagement_topics,
}: {
    profile: Profile;
    positioning: Positioning;
    assets: Asset[];
    asset_types: string[];
    engagements: Engagement[];
    engagement_types: string[];
    engagement_topics: string[];
}) {
    const { can } = usePermissions();
    const canManage = can('strategy.manage');

    const removeAsset = (id: number) => router.delete(route('strategy.brand.assets.destroy', id), { preserveScroll: true });
    const setEngagementStatus = (engagement: Engagement, status: string) =>
        router.patch(route('strategy.engagements.update', engagement.id), { status }, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Brand" />
            <div className="space-y-6 p-4">
                <Heading title="Brand" description="Positioning, messaging and visual direction, the assets that carry them, and client education" />

                <Card>
                    <CardContent className="text-muted-foreground p-4 text-sm">
                        This is where brand decisions are recorded so the rest of the platform can use them. Arriving at the positioning, and
                        producing the creative, stays human work.
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="min-w-0">
                        <CardContent className="p-4">
                            <div className="mb-2 flex items-center justify-between gap-2">
                                <h3 className="text-sm font-medium">Brand discovery</h3>
                                <Badge variant={positioning.discovery.answered === positioning.discovery.total ? 'default' : 'secondary'}>
                                    {positioning.discovery.answered}/{positioning.discovery.total} answered
                                </Badge>
                            </div>
                            <ul className="space-y-1 text-sm">
                                {positioning.discovery.items.map((item) => (
                                    <li key={item.key} className="text-muted-foreground">
                                        <span className={item.ok ? 'text-green-600' : 'text-destructive'}>{item.ok ? '✓' : '✗'}</span>{' '}
                                        <span className="text-foreground font-medium">{item.label}</span> — {item.detail}
                                    </li>
                                ))}
                            </ul>
                            <Button variant="outline" size="sm" className="mt-3" asChild>
                                <a href={route('strategy.brand.style-guide')}>Download style guide (PDF)</a>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="min-w-0">
                        <CardContent className="p-4">
                            <h3 className="mb-2 text-sm font-medium">ICP alignment</h3>
                            {positioning.icpAlignment.insufficient_data ? (
                                <p className="text-muted-foreground text-sm">
                                    Needs a defined ICP (Setup) and won deals — alignment against nothing would be fiction.
                                </p>
                            ) : (
                                <ul className="space-y-1 text-sm">
                                    {positioning.icpAlignment.checks.map((check) => (
                                        <li key={check.attribute} className="text-muted-foreground">
                                            <span className={check.aligned ? 'text-green-600' : 'text-destructive'}>{check.aligned ? '✓' : '✗'}</span>{' '}
                                            <span className="text-foreground font-medium">{check.attribute}</span>: stated "{check.stated}", wins say
                                            "{check.observed}"
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <h3 className="mt-4 mb-2 text-sm font-medium">Positioning evidence</h3>
                            <ul className="text-muted-foreground space-y-1 text-sm">
                                <li>
                                    <span className="text-foreground font-medium">Premium:</span>{' '}
                                    {positioning.evidence.premium.won_deals > 0
                                        ? `avg deal $${((positioning.evidence.premium.avg_deal_value ?? 0) / 100).toFixed(0)}, median MRR $${((positioning.evidence.premium.median_mrr ?? 0) / 100).toFixed(0)} over ${positioning.evidence.premium.won_deals} wins`
                                        : 'no wins yet — premium is a number, not an adjective'}
                                </li>
                                <li>
                                    <span className="text-foreground font-medium">Vertical:</span> {positioning.evidence.vertical.with_published_page}
                                    /{positioning.evidence.vertical.active} active verticals have a published page
                                </li>
                                <li>
                                    <span className="text-foreground font-medium">Service:</span> {positioning.evidence.service.with_published_page}/
                                    {positioning.evidence.service.active} active services have a published page
                                </li>
                                <li>
                                    <span className="text-foreground font-medium">Geographic:</span>{' '}
                                    {positioning.evidence.geographic.with_published_page}/{positioning.evidence.geographic.branches} branches have a
                                    published location page
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                {positioning.differentiators.length > 0 && (
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="mb-2 text-sm font-medium">Differentiators, checked against reality</h3>
                            <ul className="space-y-1 text-sm">
                                {positioning.differentiators.map((row) => (
                                    <li key={row.differentiator} className="text-muted-foreground">
                                        <span className="text-foreground font-medium">"{row.differentiator}"</span> —{' '}
                                        {row.on_our_site ? 'on your published pages' : 'not found on your published pages'}
                                        {row.claimed_by.length > 0 && (
                                            <span className="text-destructive">
                                                {' '}
                                                · also claimed by {row.claimed_by.join(', ')} — a table stake, not a differentiator
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {positioning.competitorMessaging.length > 0 && (
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="mb-2 text-sm font-medium">Competitor messaging (from their captured pages)</h3>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {positioning.competitorMessaging.map((row) => (
                                    <div key={row.competitor} className="min-w-0 rounded-md border p-3">
                                        <p className="text-sm font-medium">{row.competitor}</p>
                                        {row.titles.length === 0 ? (
                                            <p className="text-muted-foreground text-xs">
                                                No content captured yet — run Check content on Competitors.
                                            </p>
                                        ) : (
                                            <ul className="text-muted-foreground mt-1 list-disc pl-4 text-xs">
                                                {row.titles.map((title) => (
                                                    <li key={title} className="break-words">
                                                        {title}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div>
                    <h3 className="mb-2 text-sm font-medium">Brand profile</h3>
                    <BrandProfileForm profile={profile} canManage={canManage} />
                </div>

                <div>
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Brand assets</h3>
                        {canManage && <NewAssetDialog assetTypes={asset_types} />}
                    </div>
                    {assets.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No brand assets recorded yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Asset</th>
                                        <th className="p-3 font-medium">Type</th>
                                        <th className="p-3 font-medium">Link</th>
                                        <th className="p-3 font-medium">Notes</th>
                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {assets.map((asset) => (
                                        <tr key={asset.id} className="hover:bg-muted/40 align-top">
                                            <td className="p-3 font-medium">{asset.title}</td>
                                            <td className="p-3">
                                                <Badge variant="outline">{humanize(asset.type)}</Badge>
                                            </td>
                                            <td className="p-3">
                                                {asset.url === null ? (
                                                    <span className="text-muted-foreground">—</span>
                                                ) : (
                                                    <a
                                                        href={asset.url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="break-all underline underline-offset-4"
                                                    >
                                                        {asset.url}
                                                    </a>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground max-w-80 p-3">{asset.notes ?? '—'}</td>
                                            {canManage && (
                                                <td className="p-3">
                                                    <div className="flex justify-end">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removeAsset(asset.id)}
                                                        >
                                                            Remove
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
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Training and consulting</h3>
                        {canManage && <NewEngagementDialog engagementTypes={engagement_types} engagementTopics={engagement_topics} />}
                    </div>
                    {engagements.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No engagements scheduled.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Engagement</th>
                                        <th className="p-3 font-medium">Type</th>
                                        <th className="p-3 font-medium">Topic</th>
                                        <th className="p-3 font-medium">Scheduled</th>
                                        <th className="p-3 text-center font-medium">Attendees</th>
                                        <th className="p-3 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {engagements.map((engagement) => (
                                        <tr key={engagement.id} className="hover:bg-muted/40 align-top">
                                            <td className="max-w-80 p-3">
                                                <p className="font-medium">{engagement.title}</p>
                                                {engagement.notes && <p className="text-muted-foreground">{engagement.notes}</p>}
                                            </td>
                                            <td className="p-3">
                                                <Badge variant="outline">{humanize(engagement.type)}</Badge>
                                            </td>
                                            <td className="text-muted-foreground p-3">{engagement.topic ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{formatTime(engagement.scheduled_at)}</td>
                                            <td className="p-3 text-center">{engagement.attendees ?? '—'}</td>
                                            <td className="p-3">
                                                {canManage ? (
                                                    <Select value={engagement.status} onValueChange={(v) => setEngagementStatus(engagement, v)}>
                                                        <SelectTrigger className="w-36" aria-label={`Status of ${engagement.title}`}>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {engagementStatuses.map((status) => (
                                                                <SelectItem key={status} value={status}>
                                                                    {status}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                ) : (
                                                    <Badge variant="outline">{engagement.status}</Badge>
                                                )}
                                            </td>
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
