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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Experiments', href: '/analytics/experiments' }];

type VariantResult = {
    id: number;
    name: string;
    is_control: boolean;
    impressions: number;
    conversions: number;
    conversion_rate: number;
    lift: number;
};

type Experiment = {
    id: number;
    name: string;
    type: string;
    hypothesis: string | null;
    status: string;
    winning_variant_id: number | null;
    results: VariantResult[];
};

type VariantRow = { name: string; content?: { headline?: string } };

type PageOption = { id: number; title: string; slug: string };

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'running') return 'default';
    if (status === 'completed') return 'outline';
    return 'secondary';
}

function formatLift(lift: number): string {
    return `${lift > 0 ? '+' : ''}${lift}%`;
}

function NewExperimentDialog({ types, pages }: { types: string[]; pages: PageOption[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; type: string; hypothesis: string; site_page_id: string; variants: VariantRow[] }>({
        name: '',
        type: types[0] ?? 'landing_page',
        hypothesis: '',
        site_page_id: '',
        variants: [{ name: 'Control' }, { name: 'Variant B' }],
    });

    const updateVariant = (index: number, patch: Partial<VariantRow>) =>
        form.setData(
            'variants',
            form.data.variants.map((variant, i) => (i === index ? { ...variant, ...patch } : variant)),
        );

    const addVariant = () => form.setData('variants', [...form.data.variants, { name: '' }]);

    const removeVariant = (index: number) =>
        form.setData(
            'variants',
            form.data.variants.filter((_, i) => i !== index),
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, site_page_id: data.site_page_id === '' ? null : Number(data.site_page_id) }));
        form.post(route('analytics.experiments.store'), {
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
                <Button>New experiment</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New experiment</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="experiment_name">Name</Label>
                        <Input id="experiment_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="experiment_type">Type</Label>
                        <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                            <SelectTrigger id="experiment_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {type.replace(/_/g, ' ')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.type} />
                    </div>
                    {pages.length > 0 && (
                        <div className="grid gap-1">
                            <Label htmlFor="experiment_page">Serve on page (optional)</Label>
                            <Select value={form.data.site_page_id} onValueChange={(v) => form.setData('site_page_id', v)}>
                                <SelectTrigger id="experiment_page">
                                    <SelectValue placeholder="Not bound - record results manually" />
                                </SelectTrigger>
                                <SelectContent>
                                    {pages.map((page) => (
                                        <SelectItem key={page.id} value={String(page.id)}>
                                            {page.title} (/s/{page.slug})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-xs">
                                Bound + running: visitors are split automatically, variant headlines override the page, and form submissions convert.
                            </p>
                        </div>
                    )}
                    <div className="grid gap-1">
                        <Label htmlFor="experiment_hypothesis">Hypothesis</Label>
                        <textarea
                            id="experiment_hypothesis"
                            className={textareaClass}
                            value={form.data.hypothesis}
                            onChange={(e) => form.setData('hypothesis', e.target.value)}
                        />
                        <InputError message={form.errors.hypothesis} />
                    </div>
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label>Variants</Label>
                            <Button type="button" size="sm" variant="outline" onClick={addVariant}>
                                Add variant
                            </Button>
                        </div>
                        {form.data.variants.map((variant, index) => (
                            <div key={index} className="flex items-center gap-2">
                                <Input
                                    placeholder={index === 0 ? 'Control' : 'Variant name'}
                                    value={variant.name}
                                    onChange={(e) => updateVariant(index, { name: e.target.value })}
                                />
                                {form.data.site_page_id !== '' && index > 0 && (
                                    <Input
                                        placeholder="Headline override"
                                        value={variant.content?.headline ?? ''}
                                        onChange={(e) => updateVariant(index, { content: { headline: e.target.value } })}
                                    />
                                )}
                                {index === 0 && <Badge variant="outline">Control</Badge>}
                                {form.data.variants.length > 2 && (
                                    <Button type="button" size="sm" variant="ghost" className="text-destructive" onClick={() => removeVariant(index)}>
                                        Remove
                                    </Button>
                                )}
                            </div>
                        ))}
                        <InputError message={form.errors.variants} />
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

function RecordResultsDialog({ variant }: { variant: VariantResult }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ impressions: string; conversions: string }>({ impressions: '', conversions: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            impressions: Number(data.impressions),
            conversions: Number(data.conversions),
        }));
        form.post(route('analytics.experiments.record', variant.id), {
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
                    Record
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Record results for {variant.name}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor={`impressions_${variant.id}`}>Impressions</Label>
                            <Input
                                id={`impressions_${variant.id}`}
                                type="number"
                                min="0"
                                value={form.data.impressions}
                                onChange={(e) => form.setData('impressions', e.target.value)}
                            />
                            <InputError message={form.errors.impressions} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`conversions_${variant.id}`}>Conversions</Label>
                            <Input
                                id={`conversions_${variant.id}`}
                                type="number"
                                min="0"
                                value={form.data.conversions}
                                onChange={(e) => form.setData('conversions', e.target.value)}
                            />
                            <InputError message={form.errors.conversions} />
                        </div>
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

export default function Experiments({ experiments, types, pages }: { experiments: Experiment[]; types: string[]; pages: PageOption[] }) {
    const { can } = usePermissions();
    const canManage = can('analytics.experiments.manage');

    const start = (id: number) => router.post(route('analytics.experiments.start', id), {}, { preserveScroll: true });
    const conclude = (id: number) => router.post(route('analytics.experiments.conclude', id), {}, { preserveScroll: true });
    const remove = (id: number) => router.delete(route('analytics.experiments.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Experiments" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Experiments" description="A/B tests across landing pages, copy, offers and layouts" />
                    {canManage && <NewExperimentDialog types={types} pages={pages ?? []} />}
                </div>

                {experiments.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No experiments yet. Create one to start testing variants.</p>
                ) : (
                    <div className="space-y-4">
                        {experiments.map((experiment) => (
                            <Card key={experiment.id}>
                                <CardContent className="space-y-3 p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium">{experiment.name}</p>
                                                <Badge variant="outline">{experiment.type.replace(/_/g, ' ')}</Badge>
                                                <Badge variant={statusVariant(experiment.status)}>{experiment.status}</Badge>
                                            </div>
                                            {experiment.hypothesis && <p className="text-muted-foreground text-sm">{experiment.hypothesis}</p>}
                                        </div>
                                        {canManage && (
                                            <div className="flex shrink-0 flex-wrap gap-2">
                                                {experiment.status === 'draft' && (
                                                    <Button size="sm" variant="secondary" onClick={() => start(experiment.id)}>
                                                        Start
                                                    </Button>
                                                )}
                                                {experiment.status === 'running' && (
                                                    <Button size="sm" variant="secondary" onClick={() => conclude(experiment.id)}>
                                                        Conclude
                                                    </Button>
                                                )}
                                                <Button size="sm" variant="ghost" className="text-destructive" onClick={() => remove(experiment.id)}>
                                                    Delete
                                                </Button>
                                            </div>
                                        )}
                                    </div>

                                    {experiment.results.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">No variants yet.</p>
                                    ) : (
                                        <div className="overflow-x-auto rounded-lg border">
                                            <table className="w-full text-left text-sm">
                                                <thead className="bg-muted/50 text-muted-foreground">
                                                    <tr>
                                                        <th className="p-3 font-medium">Variant</th>
                                                        <th className="p-3 text-center font-medium">Impressions</th>
                                                        <th className="p-3 text-center font-medium">Conversions</th>
                                                        <th className="p-3 text-center font-medium">Conversion rate</th>
                                                        <th className="p-3 text-center font-medium">Lift</th>
                                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y">
                                                    {experiment.results.map((variant) => (
                                                        <tr key={variant.id} className="hover:bg-muted/40">
                                                            <td className="p-3">
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <span className="font-medium">{variant.name}</span>
                                                                    {variant.is_control && <Badge variant="secondary">Control</Badge>}
                                                                    {experiment.winning_variant_id === variant.id && <Badge>Winner</Badge>}
                                                                </div>
                                                            </td>
                                                            <td className="p-3 text-center">{variant.impressions}</td>
                                                            <td className="p-3 text-center">{variant.conversions}</td>
                                                            <td className="p-3 text-center">{variant.conversion_rate}%</td>
                                                            <td className="p-3 text-center">
                                                                {variant.is_control ? (
                                                                    <span className="text-muted-foreground">—</span>
                                                                ) : (
                                                                    formatLift(variant.lift)
                                                                )}
                                                            </td>
                                                            {canManage && (
                                                                <td className="p-3">
                                                                    <div className="flex justify-end">
                                                                        <RecordResultsDialog variant={variant} />
                                                                    </div>
                                                                </td>
                                                            )}
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
