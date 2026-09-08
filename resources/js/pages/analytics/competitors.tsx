import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Competitors', href: '/analytics/competitors' }];

type ContentSnapshot = {
    pages: number;
    new: string[];
    changed: string[];
    removed: string[];
    checked_at: string | null;
};

type Intel = {
    ads: { total: number; active: number; sample: { platform: string; headline: string; body: string; status: string; first_seen: string }[] };
    backlinks: { links: number; referring_domains: number; avg_da: number | null } | null;
    map: { keyword: string; location: string | null; position: number | null }[];
    reviews: { count: number; avg_rating: number | null; latest: string | null };
    social: { mentions: number; by_network: Record<string, number>; negative: number; positive: number };
};

type Competitor = {
    id: number;
    name: string;
    domain: string | null;
    notes: string | null;
    is_tracked: boolean;
    content: ContentSnapshot | null;
    intel: Intel | null;
};

type ShareRow = {
    domain: string;
    visibility: number;
    share: number;
};

type ShareOfVoice = {
    our_visibility: number;
    our_share: number;
    competitors: ShareRow[];
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function NewCompetitorDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; domain: string; notes: string; is_tracked: boolean }>({
        name: '',
        domain: '',
        notes: '',
        is_tracked: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('analytics.competitors.store'), {
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
                <Button>Add competitor</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add competitor</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="competitor_name">Name</Label>
                            <Input id="competitor_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="competitor_domain">Domain</Label>
                            <Input id="competitor_domain" value={form.data.domain} onChange={(e) => form.setData('domain', e.target.value)} />
                            <InputError message={form.errors.domain} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="competitor_notes">Notes</Label>
                        <textarea
                            id="competitor_notes"
                            className={textareaClass}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.data.is_tracked} onCheckedChange={(v) => form.setData('is_tracked', v === true)} />
                        Track this competitor
                    </label>
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

function EditCompetitorDialog({ competitor }: { competitor: Competitor }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; domain: string; notes: string; is_tracked: boolean }>({
        name: competitor.name,
        domain: competitor.domain ?? '',
        notes: competitor.notes ?? '',
        is_tracked: competitor.is_tracked,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('analytics.competitors.update', competitor.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Edit competitor</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor={`edit_name_${competitor.id}`}>Name</Label>
                            <Input id={`edit_name_${competitor.id}`} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`edit_domain_${competitor.id}`}>Domain</Label>
                            <Input
                                id={`edit_domain_${competitor.id}`}
                                value={form.data.domain}
                                onChange={(e) => form.setData('domain', e.target.value)}
                            />
                            <InputError message={form.errors.domain} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`edit_notes_${competitor.id}`}>Notes</Label>
                        <textarea
                            id={`edit_notes_${competitor.id}`}
                            className={textareaClass}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.data.is_tracked} onCheckedChange={(v) => form.setData('is_tracked', v === true)} />
                        Track this competitor
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

type HeadToHeadRow = {
    keyword: string;
    our_position: number | null;
    competitors: Record<string, number | null>;
    leading: boolean | null;
};
type AiShare = {
    checks: number;
    contested: number;
    our_recommendations: number;
    share: number;
    competitor_appearances: Record<string, number>;
};

export default function Competitors({
    competitors,
    share_of_voice,
    headToHead,
    aiShare,
    intel_providers,
}: {
    competitors: Competitor[];
    share_of_voice: ShareOfVoice;
    headToHead: HeadToHeadRow[];
    aiShare: AiShare;
    intel_providers: Record<string, string>;
}) {
    const competitorDomains = headToHead.length > 0 ? Object.keys(headToHead[0].competitors) : [];
    const { can } = usePermissions();
    const canManage = can('analytics.competitors.manage');

    const remove = (id: number) => router.delete(route('analytics.competitors.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Competitors" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Competitors" description="Tracked competitors and your share of search visibility" />
                    {canManage && <NewCompetitorDialog />}
                </div>

                <div className="grid grid-cols-1 gap-3 sm:max-w-md sm:grid-cols-2">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-sm">Your visibility</p>
                            <p className="text-2xl font-semibold">{share_of_voice.our_visibility}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-sm">Your share of voice</p>
                            <p className="text-2xl font-semibold">{share_of_voice.our_share}%</p>
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Share of voice</h3>
                    {share_of_voice.competitors.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No competitor rankings captured yet. Share of voice appears once competitor positions are tracked.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Domain</th>
                                        <th className="p-3 text-center font-medium">Visibility</th>
                                        <th className="p-3 text-center font-medium">Share</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {share_of_voice.competitors.map((row) => (
                                        <tr key={row.domain} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{row.domain}</td>
                                            <td className="p-3 text-center">{row.visibility}</td>
                                            <td className="text-muted-foreground p-3 text-center">{row.share}%</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Keyword head-to-head</h3>
                    {headToHead.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            Track keywords (and map them to pages) to see your position against every tracked competitor, checked daily.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Keyword</th>
                                        <th className="p-3 text-center font-medium">You</th>
                                        {competitorDomains.map((domain) => (
                                            <th key={domain} className="p-3 text-center font-medium">
                                                {domain}
                                            </th>
                                        ))}
                                        <th className="p-3 text-center font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {headToHead.map((row) => (
                                        <tr key={row.keyword} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{row.keyword}</td>
                                            <td className="p-3 text-center font-semibold tabular-nums">
                                                {row.our_position !== null ? `#${row.our_position}` : '—'}
                                            </td>
                                            {competitorDomains.map((domain) => (
                                                <td key={domain} className="text-muted-foreground p-3 text-center tabular-nums">
                                                    {row.competitors[domain] !== null ? `#${row.competitors[domain]}` : '—'}
                                                </td>
                                            ))}
                                            <td className="p-3 text-center">
                                                {row.leading === null ? (
                                                    <span className="text-muted-foreground text-xs">unmeasured</span>
                                                ) : row.leading ? (
                                                    <span className="rounded-full bg-emerald-500/12 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                                        Leading
                                                    </span>
                                                ) : (
                                                    <span className="rounded-full bg-red-500/12 px-2 py-0.5 text-xs font-semibold text-red-700 dark:text-red-300">
                                                        Behind
                                                    </span>
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
                    <h3 className="mb-2 text-sm font-medium">Share of AI recommendations</h3>
                    {aiShare.contested === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No contested AI answers recorded yet — run visibility checks to measure who AI engines recommend.
                        </p>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Card>
                                <CardContent className="p-4">
                                    <p className="text-muted-foreground text-sm">You are the recommendation</p>
                                    <p className="text-2xl font-semibold tabular-nums">{aiShare.share}%</p>
                                    <p className="text-muted-foreground text-xs">
                                        of {aiShare.contested} contested answers ({aiShare.checks} checks)
                                    </p>
                                </CardContent>
                            </Card>
                            <Card className="sm:col-span-2">
                                <CardContent className="p-4">
                                    <p className="text-muted-foreground mb-2 text-sm">Competitor appearances in AI answers</p>
                                    {Object.keys(aiShare.competitor_appearances).length === 0 ? (
                                        <p className="text-muted-foreground text-sm">No competitors detected in recorded answers.</p>
                                    ) : (
                                        <ul className="space-y-1 text-sm">
                                            {Object.entries(aiShare.competitor_appearances).map(([name, count]) => (
                                                <li key={name} className="flex items-center justify-between">
                                                    <span>{name}</span>
                                                    <span className="text-muted-foreground tabular-nums">{count}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Tracked competitors</h3>
                    {competitors.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No competitors yet. Add one to start monitoring the market.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Name</th>
                                        <th className="p-3 font-medium">Domain</th>
                                        <th className="p-3 font-medium">Notes</th>
                                        <th className="p-3 font-medium">Tracked</th>
                                        <th className="p-3 font-medium">Content</th>
                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {competitors.map((competitor) => (
                                        <tr key={competitor.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{competitor.name}</td>
                                            <td className="text-muted-foreground p-3">{competitor.domain ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{competitor.notes ?? '—'}</td>
                                            <td className="p-3">
                                                <Badge variant={competitor.is_tracked ? 'default' : 'secondary'}>
                                                    {competitor.is_tracked ? 'Tracked' : 'Paused'}
                                                </Badge>
                                            </td>
                                            <td className="p-3">
                                                {competitor.content === null ? (
                                                    <span className="text-muted-foreground text-xs">Not checked yet</span>
                                                ) : (
                                                    <div className="space-y-1 text-xs">
                                                        <span className="text-muted-foreground">
                                                            {competitor.content.pages} pages · checked{' '}
                                                            {competitor.content.checked_at
                                                                ? new Date(competitor.content.checked_at).toLocaleDateString()
                                                                : '—'}
                                                        </span>
                                                        {(competitor.content.new.length > 0 ||
                                                            competitor.content.changed.length > 0 ||
                                                            competitor.content.removed.length > 0) && (
                                                            <div className="flex flex-wrap gap-1">
                                                                {competitor.content.new.length > 0 && (
                                                                    <Badge variant="default">{competitor.content.new.length} new</Badge>
                                                                )}
                                                                {competitor.content.changed.length > 0 && (
                                                                    <Badge variant="secondary">{competitor.content.changed.length} changed</Badge>
                                                                )}
                                                                {competitor.content.removed.length > 0 && (
                                                                    <Badge variant="outline">{competitor.content.removed.length} removed</Badge>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            {canManage && (
                                                <td className="p-3">
                                                    <div className="flex justify-end gap-2">
                                                        {competitor.domain && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    router.post(
                                                                        route('analytics.competitors.check', competitor.id),
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                            >
                                                                Check content
                                                            </Button>
                                                        )}
                                                        <EditCompetitorDialog competitor={competitor} />
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => remove(competitor.id)}
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

                {/* CINT-002/003/004/006/007/009: provider-backed intel per tracked competitor */}
                {competitors.some((c) => c.intel !== null) && (
                    <div className="space-y-3">
                        <h3 className="text-sm font-medium">Competitor intel</h3>
                        {Object.values(intel_providers).includes('fixture') && (
                            <p className="text-muted-foreground text-xs">
                                Panels served by the <strong>fixture driver are simulated</strong> — connect the live providers (Meta Ad Library /
                                SerpApi / link index / review and listening APIs) to replace them with real market data. Each panel names its driver.
                            </p>
                        )}
                        {competitors
                            .filter((c) => c.intel !== null)
                            .map((competitor) => (
                                <div key={competitor.id} className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-5">
                                    <div>
                                        <p className="text-muted-foreground text-xs uppercase">Ads ({intel_providers.ads})</p>
                                        <p className="font-medium">{competitor.name}</p>
                                        <p className="text-sm tabular-nums">
                                            {competitor.intel!.ads.active} active of {competitor.intel!.ads.total}
                                        </p>
                                        {competitor.intel!.ads.sample.slice(0, 2).map((ad, i) => (
                                            <p key={i} className="text-muted-foreground text-xs">
                                                [{ad.platform}] {ad.headline}
                                            </p>
                                        ))}
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs uppercase">Backlinks ({intel_providers.backlinks})</p>
                                        {competitor.intel!.backlinks === null ? (
                                            <p className="text-muted-foreground text-sm">No domain recorded.</p>
                                        ) : (
                                            <p className="text-sm tabular-nums">
                                                {competitor.intel!.backlinks.links} links · {competitor.intel!.backlinks.referring_domains} domains ·
                                                DA {competitor.intel!.backlinks.avg_da ?? '—'}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs uppercase">Map pack ({intel_providers.map})</p>
                                        {competitor.intel!.map.length === 0 && (
                                            <p className="text-muted-foreground text-sm">No located tracked keywords.</p>
                                        )}
                                        {competitor.intel!.map.map((row) => (
                                            <p key={`${row.keyword}|${row.location}`} className="text-sm tabular-nums">
                                                {row.keyword}: {row.position !== null ? `#${row.position}` : 'not in pack'}
                                            </p>
                                        ))}
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs uppercase">Reviews ({intel_providers.reviews})</p>
                                        <p className="text-sm tabular-nums">
                                            {competitor.intel!.reviews.avg_rating ?? '—'}★ · {competitor.intel!.reviews.count} reviews
                                        </p>
                                        {competitor.intel!.reviews.latest && (
                                            <p className="text-muted-foreground text-xs">"{competitor.intel!.reviews.latest}"</p>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs uppercase">Social ({intel_providers.social})</p>
                                        <p className="text-sm tabular-nums">
                                            {competitor.intel!.social.mentions} mentions · {competitor.intel!.social.positive} positive ·{' '}
                                            {competitor.intel!.social.negative} negative
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {Object.entries(competitor.intel!.social.by_network)
                                                .map(([network, count]) => `${network} ${count}`)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                </div>
                            ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
