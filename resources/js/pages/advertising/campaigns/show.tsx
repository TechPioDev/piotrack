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
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Kpi = {
    impressions: number;
    clicks: number;
    spend: number;
    conversions: number;
    revenue: number;
    ctr: number;
    cpc: number;
    cpa: number;
    roas: number;
    conversion_rate: number;
};

type Ad = {
    id: number;
    name: string;
    headline: string | null;
    status: string;
};

type Keyword = {
    id: number;
    phrase: string;
    match_type: string;
    is_negative: boolean;
};

type Group = {
    id: number;
    name: string;
    status: string;
    bid_strategy: string;
    ads: Ad[];
    keywords: Keyword[];
};

type Campaign = {
    id: number;
    name: string;
    platform: string;
    type: string | null;
    objective: string;
    status: string;
    daily_budget: number;
    total_budget: number;
};

type Metric = {
    date: string;
    impressions: number;
    clicks: number;
    spend: number;
    conversions: number;
    revenue: number;
};

type Extension = {
    id: number;
    kind: string;
    text: string;
    url: string | null;
    phone: string | null;
};

type BidRecommendations = {
    sufficient: boolean;
    items: { rule: string; message: string }[];
};

type TrackingNumber = {
    id: number;
    phone_number: string;
    label: string | null;
};

type CallStats = {
    linked_numbers: TrackingNumber[];
    total: number;
    qualified: number;
    converted: number;
};

type BidStrategy = 'manual_cpc' | 'maximize_conversions' | 'target_cpa';
type MatchType = 'broad' | 'phrase' | 'exact';

const BID_STRATEGIES: BidStrategy[] = ['manual_cpc', 'maximize_conversions', 'target_cpa'];
const MATCH_TYPES: MatchType[] = ['broad', 'phrase', 'exact'];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function money(cents: number): string {
    return `$${(cents / 100).toFixed(2)}`;
}

function statusVariant(status: string): 'default' | 'secondary' {
    return status === 'active' ? 'default' : 'secondary';
}

function kpiCards(kpi: Kpi): { label: string; value: string | number }[] {
    return [
        { label: 'Spend', value: money(kpi.spend) },
        { label: 'Impressions', value: kpi.impressions },
        { label: 'Clicks', value: kpi.clicks },
        { label: 'CTR', value: `${kpi.ctr}%` },
        { label: 'Conversions', value: kpi.conversions },
        { label: 'CPA', value: money(kpi.cpa) },
        { label: 'ROAS', value: `${kpi.roas}x` },
    ];
}

function AddAdGroupDialog({ campaignId }: { campaignId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; bid_strategy: BidStrategy; bid_amount: string }>({
        name: '',
        bid_strategy: 'manual_cpc',
        bid_amount: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            bid_amount: data.bid_amount ? Math.round(Number(data.bid_amount) * 100) : null,
        }));
        form.post(route('ads.groups.store', campaignId), {
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
                <Button size="sm">Add ad group</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add ad group</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="group_name">Name</Label>
                        <Input id="group_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="bid_strategy">Bid strategy</Label>
                            <Select value={form.data.bid_strategy} onValueChange={(v) => form.setData('bid_strategy', v as BidStrategy)}>
                                <SelectTrigger id="bid_strategy">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {BID_STRATEGIES.map((strategy) => (
                                        <SelectItem key={strategy} value={strategy}>
                                            {strategy}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="bid_amount">Bid amount ($)</Label>
                            <Input
                                id="bid_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.bid_amount}
                                onChange={(e) => form.setData('bid_amount', e.target.value)}
                            />
                            <InputError message={form.errors.bid_amount} />
                        </div>
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

function AddAdDialog({ groupId }: { groupId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; headline: string; body: string; cta: string; destination_url: string }>({
        name: '',
        headline: '',
        body: '',
        cta: '',
        destination_url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('ads.ads.store', groupId), {
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
                    Add ad
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add ad</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="ad_name">Name</Label>
                        <Input id="ad_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="ad_headline">Headline</Label>
                        <Input id="ad_headline" value={form.data.headline} onChange={(e) => form.setData('headline', e.target.value)} />
                        <InputError message={form.errors.headline} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="ad_body">Body</Label>
                        <textarea
                            id="ad_body"
                            className={textareaClass}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="ad_cta">CTA</Label>
                            <Input id="ad_cta" value={form.data.cta} onChange={(e) => form.setData('cta', e.target.value)} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="ad_destination_url">Destination URL</Label>
                            <Input
                                id="ad_destination_url"
                                type="url"
                                value={form.data.destination_url}
                                onChange={(e) => form.setData('destination_url', e.target.value)}
                            />
                            <InputError message={form.errors.destination_url} />
                        </div>
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

function AddKeywordDialog({ groupId }: { groupId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ phrase: string; match_type: MatchType; is_negative: boolean }>({
        phrase: '',
        match_type: 'broad',
        is_negative: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('ads.keywords.store', groupId), {
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
                    Add keyword
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add keyword</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="keyword_phrase">Phrase</Label>
                        <Input id="keyword_phrase" value={form.data.phrase} onChange={(e) => form.setData('phrase', e.target.value)} />
                        <InputError message={form.errors.phrase} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="match_type">Match type</Label>
                        <Select value={form.data.match_type} onValueChange={(v) => form.setData('match_type', v as MatchType)}>
                            <SelectTrigger id="match_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {MATCH_TYPES.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.data.is_negative} onCheckedChange={(v) => form.setData('is_negative', v === true)} />
                        Negative keyword
                    </label>
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

function AddExtensionDialog({ campaignId, kinds }: { campaignId: number; kinds: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ kind: string; text: string; url: string; phone: string }>({ kind: 'sitelink', text: '', url: '', phone: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, url: data.url || null, phone: data.phone || null }));
        form.post(route('ads.extensions.store', campaignId), {
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
                <Button size="sm">Add extension</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add extension</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="ext_kind">Kind</Label>
                        <Select value={form.data.kind} onValueChange={(v) => form.setData('kind', v)}>
                            <SelectTrigger id="ext_kind">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {kinds.map((kind) => (
                                    <SelectItem key={kind} value={kind}>
                                        {kind.replace('_', ' ')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="ext_text">Text</Label>
                        <Input id="ext_text" maxLength={90} value={form.data.text} onChange={(e) => form.setData('text', e.target.value)} />
                        <InputError message={form.errors.text} />
                    </div>
                    {form.data.kind === 'sitelink' && (
                        <div className="grid gap-1">
                            <Label htmlFor="ext_url">URL</Label>
                            <Input id="ext_url" type="url" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} />
                            <InputError message={form.errors.url} />
                        </div>
                    )}
                    {form.data.kind === 'call' && (
                        <div className="grid gap-1">
                            <Label htmlFor="ext_phone">Phone</Label>
                            <Input id="ext_phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                            <InputError message={form.errors.phone} />
                        </div>
                    )}
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

function AdGroupCard({ group, canManage }: { group: Group; canManage: boolean }) {
    const deleteGroup = () => router.delete(route('ads.groups.destroy', group.id), { preserveScroll: true });
    const deleteAd = (id: number) => router.delete(route('ads.ads.destroy', id), { preserveScroll: true });
    const deleteKeyword = (id: number) => router.delete(route('ads.keywords.destroy', id), { preserveScroll: true });
    const draftCopy = () => router.post(route('ads.groups.draft-copy', group.id), {}, { preserveScroll: true });

    return (
        <Card>
            <CardContent className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <span className="font-medium">{group.name}</span>
                        <Badge variant={statusVariant(group.status)}>{group.status}</Badge>
                        <span className="text-muted-foreground text-xs">{group.bid_strategy}</span>
                    </div>
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <AddAdDialog groupId={group.id} />
                            <AddKeywordDialog groupId={group.id} />
                            <Button size="sm" variant="outline" onClick={draftCopy}>
                                Draft copy with AI
                            </Button>
                            <Button size="sm" variant="ghost" className="text-destructive" onClick={deleteGroup}>
                                Delete group
                            </Button>
                        </div>
                    )}
                </div>

                <div>
                    <h4 className="text-muted-foreground mb-2 text-xs font-medium uppercase">Ads</h4>
                    {group.ads.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No ads yet. Add an ad to this group.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {group.ads.map((ad) => (
                                <li key={ad.id} className="flex items-center justify-between gap-3 p-3">
                                    <div>
                                        <p className="text-sm font-medium">{ad.name}</p>
                                        {ad.headline && <p className="text-muted-foreground text-xs">{ad.headline}</p>}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant={statusVariant(ad.status)}>{ad.status}</Badge>
                                        {canManage && (
                                            <Button size="sm" variant="ghost" className="text-destructive" onClick={() => deleteAd(ad.id)}>
                                                Delete
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div>
                    <h4 className="text-muted-foreground mb-2 text-xs font-medium uppercase">Keywords</h4>
                    {group.keywords.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No keywords yet. Add a keyword to this group.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {group.keywords.map((keyword) => (
                                <li key={keyword.id} className="flex items-center justify-between gap-3 p-3">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm">{keyword.phrase}</span>
                                        <Badge variant="outline">{keyword.match_type}</Badge>
                                        {keyword.is_negative && <Badge variant="destructive">Negative</Badge>}
                                    </div>
                                    {canManage && (
                                        <Button size="sm" variant="ghost" className="text-destructive" onClick={() => deleteKeyword(keyword.id)}>
                                            Delete
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

export default function CampaignShow({
    campaign,
    groups,
    kpi,
    metrics,
    extensions,
    extension_kinds,
    bid_recommendations,
    calls,
    available_numbers,
    export_formats,
    audiences,
    attached_audience,
}: {
    campaign: Campaign;
    groups: Group[];
    kpi: Kpi;
    metrics: Metric[];
    extensions: Extension[];
    extension_kinds: string[];
    bid_recommendations: BidRecommendations;
    calls: CallStats;
    available_numbers: TrackingNumber[];
    export_formats: string[];
    audiences: { id: number; name: string; member_count: number }[];
    attached_audience: string | null;
}) {
    const { can } = usePermissions();
    const canManage = can('ads.campaigns.manage');
    const page = usePage<SharedData>();
    const aiResult = (page.props.flash as { ai_result?: string } | undefined)?.ai_result ?? null;
    const [numberId, setNumberId] = useState('');
    const [audienceId, setAudienceId] = useState('');
    const isLinkedIn = campaign.platform === 'linkedin';
    const isMeta = campaign.platform === 'meta';
    const isSocialAds = isLinkedIn || isMeta;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Campaigns', href: '/ads/campaigns' },
        { title: campaign.name, href: `/ads/campaigns/${campaign.id}` },
    ];

    const setStatus = (status: string) => router.post(route('ads.campaigns.status', campaign.id), { status }, { preserveScroll: true });
    const refreshMetrics = () => router.post(route('ads.campaigns.refresh-metrics', campaign.id), {}, { preserveScroll: true });
    const bidAdvice = () => router.post(route('ads.campaigns.bid-advice', campaign.id), {}, { preserveScroll: true });
    const createLandingPage = () => router.post(route('ads.campaigns.landing-page', campaign.id), {}, { preserveScroll: true });
    const deleteExtension = (id: number) => router.delete(route('ads.extensions.destroy', id), { preserveScroll: true });
    const linkNumber = () => {
        if (numberId !== '') {
            router.post(route('ads.campaigns.tracking-number', campaign.id), { call_tracking_number_id: Number(numberId) }, { preserveScroll: true });
        }
    };
    const attachAudience = () => {
        if (audienceId !== '') {
            router.post(route('ads.campaigns.audience', campaign.id), { audience_id: Number(audienceId) }, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={campaign.name} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Heading title={campaign.name} description={campaign.objective} />
                        <Badge variant="outline">{campaign.platform}</Badge>
                        <Badge variant={statusVariant(campaign.status)}>{campaign.status}</Badge>
                    </div>
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button size="sm" variant="secondary" disabled={campaign.status === 'active'} onClick={() => setStatus('active')}>
                                Activate
                            </Button>
                            <Button size="sm" variant="secondary" disabled={campaign.status === 'paused'} onClick={() => setStatus('paused')}>
                                Pause
                            </Button>
                            <Button size="sm" variant="secondary" disabled={campaign.status === 'ended'} onClick={() => setStatus('ended')}>
                                End
                            </Button>
                            <Button size="sm" onClick={refreshMetrics}>
                                Refresh metrics
                            </Button>
                            {!isSocialAds &&
                                export_formats.map((format) => (
                                    <Button key={format} size="sm" variant="outline" asChild>
                                        <a href={`${route('ads.campaigns.export', campaign.id)}?format=${format}`}>
                                            {format === 'google' ? 'Google Ads Editor CSV' : 'Microsoft Ads CSV'}
                                        </a>
                                    </Button>
                                ))}
                            {isLinkedIn && (
                                <Button size="sm" variant="outline" asChild>
                                    <a href={route('ads.campaigns.brief', campaign.id)}>Campaign Manager brief</a>
                                </Button>
                            )}
                            <Button size="sm" variant="outline" onClick={createLandingPage}>
                                Create landing page
                            </Button>
                        </div>
                    )}
                </div>

                {isSocialAds && (
                    <Card>
                        <CardContent className="space-y-2 p-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 className="text-sm font-medium">{isMeta ? 'Custom audience' : 'Matched audience'}</h3>
                                {canManage && audiences.length > 0 && (
                                    <div className="flex items-center gap-2">
                                        <Select value={audienceId} onValueChange={setAudienceId}>
                                            <SelectTrigger className="h-8 w-56">
                                                <SelectValue placeholder="Choose an audience" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {audiences.map((a) => (
                                                    <SelectItem key={a.id} value={String(a.id)}>
                                                        {a.name} ({a.member_count})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Button size="sm" variant="outline" onClick={attachAudience} disabled={audienceId === ''}>
                                            Attach
                                        </Button>
                                    </div>
                                )}
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {attached_audience
                                    ? isMeta
                                        ? `Attached: ${attached_audience}. Upload its export CSV in Ads Manager (Audiences → Customer list) and select it there — live audience push needs the Meta API.`
                                        : `Attached: ${attached_audience}. Upload its export CSV in Campaign Manager (Plan → Audiences) and select it there — live audience push needs the LinkedIn API.`
                                    : isMeta
                                      ? 'Attach a retargeting audience, then upload its export CSV in Ads Manager as the custom audience.'
                                      : 'Attach a retargeting audience, then upload its export CSV in Campaign Manager as the matched audience.'}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {aiResult && (
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground mb-1 text-xs font-medium uppercase">AI result — advisory only</p>
                            <pre className="text-sm whitespace-pre-wrap">{aiResult}</pre>
                        </CardContent>
                    </Card>
                )}

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
                    {kpiCards(kpi).map((card) => (
                        <Card key={card.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{card.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="space-y-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Ad groups</h3>
                        {canManage && <AddAdGroupDialog campaignId={campaign.id} />}
                    </div>
                    {groups.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No ad groups yet. Add an ad group to organize your ads and keywords.</p>
                    ) : (
                        <div className="space-y-3">
                            {groups.map((group) => (
                                <AdGroupCard key={group.id} group={group} canManage={canManage} />
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid gap-3 lg:grid-cols-2">
                    <Card>
                        <CardContent className="space-y-2 p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="text-sm font-medium">Bid guidance</h3>
                                {canManage && (
                                    <Button size="sm" variant="outline" onClick={bidAdvice}>
                                        AI bid advice
                                    </Button>
                                )}
                            </div>
                            {!bid_recommendations.sufficient ? (
                                <p className="text-muted-foreground text-sm">
                                    Not enough recorded clicks in the last 30 days to recommend bid changes — guidance appears once the campaign has
                                    real data.
                                </p>
                            ) : bid_recommendations.items.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No bid issues detected from the last 30 days of metrics.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {bid_recommendations.items.map((item) => (
                                        <li key={item.rule} className="rounded-lg border p-3 text-sm">
                                            {item.message}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <p className="text-muted-foreground text-xs">
                                Advisory only — apply changes on the ad groups. Automated live bidding requires the ads platform API connection.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="space-y-2 p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="text-sm font-medium">Call tracking</h3>
                                {canManage && available_numbers.length > 0 && (
                                    <div className="flex items-center gap-2">
                                        <Select value={numberId} onValueChange={setNumberId}>
                                            <SelectTrigger className="h-8 w-44">
                                                <SelectValue placeholder="Link a number" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {available_numbers.map((n) => (
                                                    <SelectItem key={n.id} value={String(n.id)}>
                                                        {n.label ?? n.phone_number}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Button size="sm" variant="outline" onClick={linkNumber} disabled={numberId === ''}>
                                            Link
                                        </Button>
                                    </div>
                                )}
                            </div>
                            {calls.linked_numbers.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No tracking number linked. Link one so calls it receives attribute to this campaign.
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    <div className="grid grid-cols-3 gap-2 text-center">
                                        <div className="rounded-lg border p-2">
                                            <p className="text-lg font-semibold">{calls.total}</p>
                                            <p className="text-muted-foreground text-xs">Calls</p>
                                        </div>
                                        <div className="rounded-lg border p-2">
                                            <p className="text-lg font-semibold">{calls.qualified}</p>
                                            <p className="text-muted-foreground text-xs">Qualified</p>
                                        </div>
                                        <div className="rounded-lg border p-2">
                                            <p className="text-lg font-semibold">{calls.converted}</p>
                                            <p className="text-muted-foreground text-xs">Converted</p>
                                        </div>
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        Numbers: {calls.linked_numbers.map((n) => n.label ?? n.phone_number).join(', ')}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Extensions &amp; assets</h3>
                        {canManage && <AddExtensionDialog campaignId={campaign.id} kinds={extension_kinds} />}
                    </div>
                    {extensions.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No extensions yet. Sitelinks, callouts and snippets lift CTR at no extra cost and ship with the editor export.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {extensions.map((ext) => (
                                <li key={ext.id} className="flex items-center justify-between gap-3 p-3">
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">{ext.kind.replace('_', ' ')}</Badge>
                                        <span className="text-sm">{ext.text}</span>
                                        {ext.url && <span className="text-muted-foreground text-xs">{ext.url}</span>}
                                        {ext.phone && <span className="text-muted-foreground text-xs">{ext.phone}</span>}
                                    </div>
                                    {canManage && (
                                        <Button size="sm" variant="ghost" className="text-destructive" onClick={() => deleteExtension(ext.id)}>
                                            Delete
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Metrics</h3>
                    {metrics.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No metrics yet. Use &ldquo;Refresh metrics&rdquo; to pull the latest data.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Date</th>
                                        <th className="p-3 text-center font-medium">Impressions</th>
                                        <th className="p-3 text-center font-medium">Clicks</th>
                                        <th className="p-3 text-center font-medium">Spend</th>
                                        <th className="p-3 text-center font-medium">Conversions</th>
                                        <th className="p-3 text-center font-medium">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {metrics.map((metric) => (
                                        <tr key={metric.date} className="hover:bg-muted/40">
                                            <td className="p-3">{metric.date}</td>
                                            <td className="p-3 text-center">{metric.impressions}</td>
                                            <td className="p-3 text-center">{metric.clicks}</td>
                                            <td className="p-3 text-center">{money(metric.spend)}</td>
                                            <td className="p-3 text-center">{metric.conversions}</td>
                                            <td className="p-3 text-center">{money(metric.revenue)}</td>
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
