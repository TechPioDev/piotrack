import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type CampaignStats = {
    recipients: number;
    sent: number;
    opened: number;
    clicked: number;
    bounced: number;
    unsubscribed: number;
};

type AbVariant = { subject: string | null; recipients: number; opens: number; open_rate: number };
type AbResults = { enabled: boolean; variants: Record<string, AbVariant>; leader: string | null } | null;
type Conversions = { customers: number; revenue: number };

type Campaign = {
    id: number;
    name: string;
    channel: string;
    type: string | null;
    subject: string | null;
    subject_b: string | null;
    ab: AbResults;
    conversions: Conversions;
    from_name: string | null;
    from_email: string | null;
    body_html: string | null;
    body_text: string | null;
    status: string;
    marketing_list_id: number | null;
    vertical_id: number | null;
    video_url: string | null;
    video_title: string | null;
    stats: CampaignStats;
};

type ListOption = { id: number; name: string };

const STAT_CARDS: { key: keyof CampaignStats; label: string }[] = [
    { key: 'recipients', label: 'Recipients' },
    { key: 'sent', label: 'Sent' },
    { key: 'opened', label: 'Opened' },
    { key: 'clicked', label: 'Clicked' },
    { key: 'bounced', label: 'Bounced' },
    { key: 'unsubscribed', label: 'Unsubscribed' },
];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

export default function CampaignShow({
    campaign,
    lists,
    verticals,
}: {
    campaign: Campaign;
    lists: ListOption[];
    verticals: { id: number; name: string }[];
}) {
    const { can } = usePermissions();
    const canManage = can('marketing.campaigns.manage');
    const canSend = can('marketing.campaigns.send');

    const form = useForm<{
        name: string;
        subject: string;
        subject_b: string;
        from_name: string;
        from_email: string;
        body_html: string;
        body_text: string;
        marketing_list_id: string;
        vertical_id: string;
        video_url: string;
        video_title: string;
    }>({
        name: campaign.name,
        subject: campaign.subject ?? '',
        subject_b: campaign.subject_b ?? '',
        from_name: campaign.from_name ?? '',
        from_email: campaign.from_email ?? '',
        body_html: campaign.body_html ?? '',
        body_text: campaign.body_text ?? '',
        marketing_list_id: campaign.marketing_list_id ? String(campaign.marketing_list_id) : '',
        vertical_id: campaign.vertical_id ? String(campaign.vertical_id) : '',
        video_url: campaign.video_url ?? '',
        video_title: campaign.video_title ?? '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Campaigns', href: '/marketing/campaigns' },
        { title: campaign.name, href: `/marketing/campaigns/${campaign.id}` },
    ];

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('marketing.campaigns.update', campaign.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={campaign.name} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Heading title={campaign.name} description={campaign.channel} />
                        <Badge variant={campaign.status === 'sent' ? 'default' : 'secondary'}>{campaign.status}</Badge>
                    </div>
                    {canSend && (
                        <Button
                            disabled={campaign.status === 'sent'}
                            onClick={() => router.post(route('marketing.campaigns.send', campaign.id), {}, { preserveScroll: true })}
                        >
                            Send campaign
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {STAT_CARDS.map((card) => (
                        <Card key={card.key}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{campaign.stats[card.key]}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-sm">Customers converted</p>
                            <p className="text-2xl font-semibold">{campaign.conversions.customers}</p>
                            <p className="text-muted-foreground text-xs">won after this campaign was sent</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-sm">Attributed revenue</p>
                            <p className="text-2xl font-semibold">${(campaign.conversions.revenue / 100).toLocaleString('en-US')}</p>
                            <p className="text-muted-foreground text-xs">closed-won deals of recipients, post-send only</p>
                        </CardContent>
                    </Card>
                    {campaign.ab !== null && (
                        <Card>
                            <CardContent className="space-y-1 p-4">
                                <p className="text-sm font-medium">Subject A/B</p>
                                {Object.entries(campaign.ab.variants).map(([variant, row]) => (
                                    <p key={variant} className="text-muted-foreground text-xs">
                                        <span className="font-medium uppercase">{variant}</span> · {row.opens}/{row.recipients} opened (
                                        {row.open_rate}%){campaign.ab?.leader === variant && ' — leading'}
                                    </p>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardContent className="p-4">
                        <form onSubmit={save} className="space-y-3">
                            <div className="grid gap-1">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="subject">Subject</Label>
                                    <Input id="subject" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="subject_b">Subject B (optional A/B test)</Label>
                                    <Input
                                        id="subject_b"
                                        placeholder="Leave empty to send one subject"
                                        value={form.data.subject_b}
                                        onChange={(e) => form.setData('subject_b', e.target.value)}
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="from_name">From name</Label>
                                    <Input id="from_name" value={form.data.from_name} onChange={(e) => form.setData('from_name', e.target.value)} />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="from_email">From email</Label>
                                    <Input
                                        id="from_email"
                                        type="email"
                                        value={form.data.from_email}
                                        onChange={(e) => form.setData('from_email', e.target.value)}
                                    />
                                </div>
                            </div>
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
                            </div>
                            {verticals.length > 0 && (
                                <div className="grid gap-1">
                                    <Label htmlFor="campaign_vertical">Vertical (optional)</Label>
                                    <Select value={form.data.vertical_id} onValueChange={(v) => form.setData('vertical_id', v)}>
                                        <SelectTrigger id="campaign_vertical">
                                            <SelectValue placeholder="Not bound to one vertical" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {verticals.map((vertical) => (
                                                <SelectItem key={vertical.id} value={String(vertical.id)}>
                                                    {vertical.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="video_url">Video URL (optional)</Label>
                                    <Input
                                        id="video_url"
                                        type="url"
                                        value={form.data.video_url}
                                        onChange={(e) => form.setData('video_url', e.target.value)}
                                        placeholder="https://youtu.be/…"
                                    />
                                    <InputError message={form.errors.video_url} />
                                    <p className="text-muted-foreground text-xs">
                                        Appended as a click-tracked watch button — email clients never play video inline, so plays are measured as
                                        clicks.
                                    </p>
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="video_title">Video title</Label>
                                    <Input
                                        id="video_title"
                                        value={form.data.video_title}
                                        onChange={(e) => form.setData('video_title', e.target.value)}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="body_html">Body HTML</Label>
                                <textarea
                                    id="body_html"
                                    className={textareaClass}
                                    value={form.data.body_html}
                                    onChange={(e) => form.setData('body_html', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="body_text">Body text</Label>
                                <textarea
                                    id="body_text"
                                    className={textareaClass}
                                    value={form.data.body_text}
                                    onChange={(e) => form.setData('body_text', e.target.value)}
                                />
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Use {'{{first_name}}'}, {'{{last_name}}'}, {'{{full_name}}'}, {'{{email}}'}, {'{{company}}'}
                            </p>
                            {canManage && (
                                <Button type="submit" disabled={form.processing}>
                                    Save
                                </Button>
                            )}
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
