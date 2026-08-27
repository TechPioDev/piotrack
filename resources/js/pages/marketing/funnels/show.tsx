import { BarList } from '@/components/charts/bar-list';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ExternalLink, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Asset = { id: number; type: string; type_label: string; name: string; url: string };
type Stage = {
    id: number;
    name: string;
    category: string;
    lifecycle_stage: string | null;
    count: number;
    at_or_beyond: number | null;
    conversion_pct: number | null;
    assets: Asset[];
    gap: boolean;
};
type Attachable = Record<string, { label: string; options: { id: number; name: string }[] }>;

const CATEGORY_STYLES: Record<string, string> = {
    tof: 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
    mof: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    bof: 'bg-brand-soft text-brand-strong',
    post: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
};

function AttachControls({ funnelId, stage, attachable }: { funnelId: number; stage: Stage; attachable: Attachable }) {
    const [type, setType] = useState<string>('');
    const [assetId, setAssetId] = useState<string>('');
    const options = type ? (attachable[type]?.options ?? []) : [];

    return (
        <div className="mt-3 flex flex-wrap items-center gap-2">
            <Select
                value={type || '__pick'}
                onValueChange={(v) => {
                    setType(v === '__pick' ? '' : v);
                    setAssetId('');
                }}
            >
                <SelectTrigger className="h-8 w-40" aria-label={`Asset type for ${stage.name}`}>
                    <SelectValue placeholder="Add asset…" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="__pick">Add asset…</SelectItem>
                    {Object.entries(attachable).map(([key, group]) => (
                        <SelectItem key={key} value={key}>
                            {group.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {type && (
                <>
                    <Select value={assetId || '__pick'} onValueChange={(v) => setAssetId(v === '__pick' ? '' : v)}>
                        <SelectTrigger className="h-8 w-56" aria-label={`Record to attach to ${stage.name}`}>
                            <SelectValue placeholder="Choose…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__pick">Choose…</SelectItem>
                            {options.length === 0 && (
                                <SelectItem value="__none" disabled>
                                    Nothing to attach yet
                                </SelectItem>
                            )}
                            {options.map((option) => (
                                <SelectItem key={option.id} value={String(option.id)}>
                                    {option.name || `#${option.id}`}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button
                        size="sm"
                        disabled={!assetId}
                        onClick={() => {
                            router.post(
                                route('marketing.funnels.assets.attach', [funnelId, stage.id]),
                                { asset_type: type, asset_id: Number(assetId) },
                                { preserveScroll: true, onSuccess: () => setAssetId('') },
                            );
                        }}
                    >
                        Attach
                    </Button>
                </>
            )}
        </div>
    );
}

export default function FunnelShow({
    funnel,
    stages,
    attachable,
}: {
    funnel: { id: number; name: string; description: string | null };
    stages: Stage[];
    attachable: Attachable;
}) {
    const { can } = usePermissions();
    const canManage = can('marketing.campaigns.manage');
    const gaps = stages.filter((s) => s.gap).length;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Marketing', href: '/marketing' },
        { title: 'Funnels', href: '/marketing/funnels' },
        { title: funnel.name, href: `/marketing/funnels/${funnel.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={funnel.name} />
            <div className="space-y-4 p-4">
                <PageHeader
                    title={funnel.name}
                    description={funnel.description ?? 'Each stage carries the assets that work it; conversion is measured from real contacts.'}
                    actions={
                        gaps > 0 ? (
                            <span className="flex items-center gap-1.5 rounded-full bg-amber-500/15 px-3 py-1 text-sm font-semibold text-amber-700 dark:text-amber-300">
                                <AlertTriangle className="size-3.5" aria-hidden />
                                {gaps} {gaps === 1 ? 'stage has' : 'stages have'} no assets
                            </span>
                        ) : undefined
                    }
                />

                <Card>
                    <CardContent className="p-4">
                        <h2 className="text-sm font-semibold">Contacts by stage</h2>
                        <BarList
                            className="mt-3"
                            items={stages.map((s) => ({
                                label: s.name,
                                value: s.count,
                                hint: s.conversion_pct !== null ? `${s.conversion_pct}% carried from previous` : undefined,
                            }))}
                            ariaLabel="Contacts currently at each funnel stage"
                            emptyText="No contacts in any mapped stage yet."
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    {stages.map((stage) => (
                        <Card key={stage.id} className={stage.gap ? 'border-amber-500/40' : undefined}>
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <h3 className="text-sm font-semibold">{stage.name}</h3>
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-xs font-semibold uppercase ${CATEGORY_STYLES[stage.category] ?? 'bg-muted text-muted-foreground'}`}
                                    >
                                        {stage.category}
                                    </span>
                                </div>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {stage.lifecycle_stage
                                        ? `Mapped to ${stage.lifecycle_stage.replace(/_/g, ' ')} · ${stage.count} contacts now`
                                        : 'Not mapped to a lifecycle stage'}
                                </p>

                                {stage.assets.length === 0 ? (
                                    <p className="mt-3 flex items-center gap-1.5 text-sm text-amber-700 dark:text-amber-300">
                                        <AlertTriangle className="size-3.5 shrink-0" aria-hidden />
                                        Nothing works this stage yet — attach the asset that should.
                                    </p>
                                ) : (
                                    <ul className="mt-3 space-y-1.5">
                                        {stage.assets.map((asset) => (
                                            <li key={asset.id} className="flex items-center justify-between gap-2 text-sm">
                                                <span className="flex min-w-0 items-center gap-1.5">
                                                    <Badge variant="outline" className="shrink-0 text-[10px]">
                                                        {asset.type_label}
                                                    </Badge>
                                                    <Link href={asset.url} className="hover:text-brand-strong truncate font-medium hover:underline">
                                                        {asset.name || 'Untitled'}
                                                    </Link>
                                                    <ExternalLink className="text-muted-foreground size-3 shrink-0" aria-hidden />
                                                </span>
                                                {canManage && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="shrink-0 text-red-600"
                                                        aria-label={`Remove ${asset.name} from ${stage.name}`}
                                                        onClick={() =>
                                                            router.delete(route('marketing.funnels.assets.detach', [funnel.id, stage.id, asset.id]), {
                                                                preserveScroll: true,
                                                            })
                                                        }
                                                    >
                                                        <Trash2 className="size-3.5" aria-hidden />
                                                    </Button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                {canManage && <AttachControls funnelId={funnel.id} stage={stage} attachable={attachable} />}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
