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
import { TriangleAlert } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Performance', href: '/strategy/performance' }];

type TargetRow = {
    actual: number;
    target: number;
    attainment: number;
    met: boolean;
};

type Attainment = {
    targets: {
        leads: TargetRow;
        sqls: TargetRow;
        meetings: TargetRow;
    };
    replaced_leads: number;
    all_targets_met: boolean;
    status: string;
    sla_days: number;
    period: { start: string | null; end: string | null };
};

type ReconItem = {
    promised: string;
    matched: string | null;
    status: string | null;
    approved: boolean;
    delivered: boolean;
};

type Reconciliation = {
    items: ReconItem[];
    promised: number;
    delivered: number;
    approved: number;
    all_delivered: boolean;
};

type Review = {
    id: number;
    agreement: string | null;
    period_start: string | null;
    period_end: string | null;
    won_revenue: number;
    ad_spend: number;
    roi: number | null;
    all_targets_met: boolean;
    created_at: string | null;
};

type Agreement = {
    id: number;
    name: string;
    model: string;
    lead_target: number;
    sql_target: number;
    meeting_target: number;
    quality_criteria: Record<string, unknown> | null;
    deliverables: string[] | null;
    sla_days: number;
    status: string;
    period_start: string | null;
    period_end: string | null;
    attainment: Attainment;
    reconciliation: Reconciliation;
};

type Replacement = {
    id: number;
    contact_id: number;
    reason: string;
    replaced_at: string | null;
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

const humanize = (value: string) => value.replace(/_/g, ' ');

function formatTime(iso: string | null): string {
    return iso === null ? '—' : new Date(iso).toLocaleString();
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'breached') return 'destructive';
    if (status === 'completed') return 'default';
    if (status === 'in_progress') return 'secondary';

    return 'outline';
}

function statusExplanation(status: string): string {
    if (status === 'breached')
        return 'The period closed with targets unmet. This is a missed guarantee — the commitment in the agreement was not met.';
    if (status === 'completed') return 'The period closed with every target met.';
    if (status === 'in_progress') return 'The period is still open, so the outcome is not decided yet.';

    return 'This agreement is not currently active.';
}

/** A target bar that reads correctly past 100% by clamping the fill, not the number. */
function TargetBar({ label, row }: { label: string; row: TargetRow }) {
    return (
        <div className="space-y-1">
            <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span className="font-medium">{label}</span>
                <span className="flex items-center gap-2">
                    <span className="text-muted-foreground">
                        {row.actual} of {row.target} ({row.attainment}%)
                    </span>
                    {row.target === 0 ? (
                        <Badge variant="outline">No target</Badge>
                    ) : row.met ? (
                        <Badge>Met</Badge>
                    ) : (
                        <Badge variant="destructive">Short</Badge>
                    )}
                </span>
            </div>
            <div className="bg-muted h-2 overflow-hidden rounded-full">
                <div
                    className={row.met ? 'bg-primary h-full rounded-full' : 'bg-destructive h-full rounded-full'}
                    style={{ width: `${Math.min(100, Math.max(0, row.attainment))}%` }}
                />
            </div>
        </div>
    );
}

function NewAgreementDialog({ models }: { models: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        name: string;
        model: string;
        lead_target: string;
        sql_target: string;
        meeting_target: string;
        sla_days: string;
        period_start: string;
        period_end: string;
    }>({
        name: '',
        model: models[0] ?? 'guarantee',
        lead_target: '',
        sql_target: '',
        meeting_target: '',
        sla_days: '',
        period_start: '',
        period_end: '',
    });

    const toNumber = (value: string) => (value === '' ? null : Number(value));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            lead_target: toNumber(data.lead_target),
            sql_target: toNumber(data.sql_target),
            meeting_target: toNumber(data.meeting_target),
            sla_days: toNumber(data.sla_days),
        }));
        form.post(route('strategy.performance.store'), {
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
                <Button>New agreement</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New performance agreement</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="agreement_name">Name</Label>
                        <Input id="agreement_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="agreement_model">Model</Label>
                        <Select value={form.data.model} onValueChange={(v) => form.setData('model', v)}>
                            <SelectTrigger id="agreement_model">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {models.map((model) => (
                                    <SelectItem key={model} value={model}>
                                        {humanize(model)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.model} />
                    </div>
                    <div className="grid grid-cols-4 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="agreement_leads">Lead target</Label>
                            <Input
                                id="agreement_leads"
                                type="number"
                                min="0"
                                value={form.data.lead_target}
                                onChange={(e) => form.setData('lead_target', e.target.value)}
                            />
                            <InputError message={form.errors.lead_target} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="agreement_sqls">SQL target</Label>
                            <Input
                                id="agreement_sqls"
                                type="number"
                                min="0"
                                value={form.data.sql_target}
                                onChange={(e) => form.setData('sql_target', e.target.value)}
                            />
                            <InputError message={form.errors.sql_target} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="agreement_meetings">Meeting target</Label>
                            <Input
                                id="agreement_meetings"
                                type="number"
                                min="0"
                                value={form.data.meeting_target}
                                onChange={(e) => form.setData('meeting_target', e.target.value)}
                            />
                            <InputError message={form.errors.meeting_target} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="agreement_sla">SLA days</Label>
                            <Input
                                id="agreement_sla"
                                type="number"
                                min="1"
                                value={form.data.sla_days}
                                onChange={(e) => form.setData('sla_days', e.target.value)}
                            />
                            <InputError message={form.errors.sla_days} />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="agreement_start">Period start</Label>
                            <Input
                                id="agreement_start"
                                type="date"
                                value={form.data.period_start}
                                onChange={(e) => form.setData('period_start', e.target.value)}
                            />
                            <InputError message={form.errors.period_start} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="agreement_end">Period end</Label>
                            <Input
                                id="agreement_end"
                                type="date"
                                value={form.data.period_end}
                                onChange={(e) => form.setData('period_end', e.target.value)}
                            />
                            <InputError message={form.errors.period_end} />
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

/**
 * Recording a replacement removes the lead from the delivered count under the
 * guarantee, so the reason is mandatory — it is the record of why the lead
 * failed the quality bar.
 */
function ReplaceLeadDialog({ agreement }: { agreement: Agreement }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ contact_id: string; replacement_contact_id: string; reason: string }>({
        contact_id: '',
        replacement_contact_id: '',
        reason: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            contact_id: Number(data.contact_id),
            replacement_contact_id: data.replacement_contact_id === '' ? null : Number(data.replacement_contact_id),
            reason: data.reason,
        }));
        form.post(route('strategy.performance.replace-lead', agreement.id), {
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
                    Record replacement
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Record a replaced lead on {agreement.name}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <p className="text-muted-foreground text-sm">
                        A replaced lead is excluded from the delivered count, so attainment reflects only leads that met the quality bar.
                    </p>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor={`replace_contact_${agreement.id}`}>Rejected contact id</Label>
                            <Input
                                id={`replace_contact_${agreement.id}`}
                                type="number"
                                min="1"
                                value={form.data.contact_id}
                                onChange={(e) => form.setData('contact_id', e.target.value)}
                            />
                            <InputError message={form.errors.contact_id} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`replace_with_${agreement.id}`}>Replacement contact id</Label>
                            <Input
                                id={`replace_with_${agreement.id}`}
                                type="number"
                                min="1"
                                value={form.data.replacement_contact_id}
                                onChange={(e) => form.setData('replacement_contact_id', e.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">Optional — leave blank if no replacement has been supplied yet.</p>
                            <InputError message={form.errors.replacement_contact_id} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`replace_reason_${agreement.id}`}>Reason (required)</Label>
                        <textarea
                            id={`replace_reason_${agreement.id}`}
                            className={textareaClass}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            placeholder="Which quality criterion the lead failed"
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || form.data.reason.trim() === '' || form.data.contact_id === ''}>
                            Record replacement
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function StrategyPerformance({
    agreements,
    replacements,
    models,
    reviews,
}: {
    agreements: Agreement[];
    replacements: Replacement[];
    models: string[];
    reviews: Review[];
}) {
    const { can } = usePermissions();
    const canManage = can('strategy.manage');

    const remove = (id: number) => router.delete(route('strategy.performance.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Performance" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Performance agreements"
                        description="Lead guarantees measured against the real funnel, net of leads that failed the quality bar"
                    />
                    {canManage && <NewAgreementDialog models={models} />}
                </div>

                {agreements.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No performance agreements yet.</p>
                ) : (
                    <div className="space-y-4">
                        {agreements.map((agreement) => {
                            const breached = agreement.attainment.status === 'breached';

                            return (
                                <Card key={agreement.id} className={breached ? 'border-destructive border-2' : undefined}>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-medium">{agreement.name}</p>
                                                    <Badge variant="outline">{humanize(agreement.model)}</Badge>
                                                    <Badge variant={statusVariant(agreement.attainment.status)} className="gap-1">
                                                        {breached && <TriangleAlert className="size-3" aria-hidden="true" />}
                                                        {humanize(agreement.attainment.status)}
                                                    </Badge>
                                                </div>
                                                <p className={breached ? 'text-destructive text-sm font-medium' : 'text-muted-foreground text-sm'}>
                                                    {statusExplanation(agreement.attainment.status)}
                                                </p>
                                                <p className="text-muted-foreground text-sm">
                                                    {agreement.attainment.period.start ?? '—'} → {agreement.attainment.period.end ?? '—'} ·{' '}
                                                    {agreement.attainment.sla_days} day SLA
                                                </p>
                                            </div>
                                            {canManage && (
                                                <div className="flex shrink-0 flex-wrap gap-2">
                                                    <ReplaceLeadDialog agreement={agreement} />
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive"
                                                        onClick={() => remove(agreement.id)}
                                                    >
                                                        Delete
                                                    </Button>
                                                </div>
                                            )}
                                        </div>

                                        <div className="space-y-3 rounded-lg border p-3">
                                            <TargetBar label="Leads" row={agreement.attainment.targets.leads} />
                                            <TargetBar label="SQLs" row={agreement.attainment.targets.sqls} />
                                            <TargetBar label="Meetings" row={agreement.attainment.targets.meetings} />
                                            <div className="flex flex-wrap items-center gap-3 border-t pt-3 text-sm">
                                                <span className="text-muted-foreground">
                                                    {agreement.attainment.replaced_leads} replaced lead
                                                    {agreement.attainment.replaced_leads === 1 ? '' : 's'} excluded from the delivered count
                                                </span>
                                                {agreement.attainment.all_targets_met ? (
                                                    <Badge>All targets met</Badge>
                                                ) : (
                                                    <Badge variant="destructive">Targets not met</Badge>
                                                )}
                                            </div>
                                        </div>

                                        {agreement.reconciliation.promised > 0 && (
                                            <div className="space-y-2 rounded-lg border p-3">
                                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                                    <span className="font-medium">Guaranteed deliverables</span>
                                                    <Badge variant={agreement.reconciliation.all_delivered ? 'default' : 'secondary'}>
                                                        {agreement.reconciliation.delivered}/{agreement.reconciliation.promised} delivered
                                                    </Badge>
                                                    <span className="text-muted-foreground text-xs">
                                                        Reconciled automatically against project deliverables and approvals.
                                                    </span>
                                                </div>
                                                <ul className="divide-y rounded border">
                                                    {agreement.reconciliation.items.map((item) => (
                                                        <li key={item.promised} className="flex flex-wrap items-center gap-2 p-2 text-sm">
                                                            <span className="font-medium">{item.promised}</span>
                                                            {item.delivered ? (
                                                                <Badge>{item.approved ? 'approved' : 'delivered'}</Badge>
                                                            ) : item.matched ? (
                                                                <Badge variant="secondary">{item.status ?? 'in progress'}</Badge>
                                                            ) : (
                                                                <Badge variant="destructive">missing</Badge>
                                                            )}
                                                            {item.matched && item.matched !== item.promised && (
                                                                <span className="text-muted-foreground text-xs">matched: {item.matched}</span>
                                                            )}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        )}
                                        {canManage && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(route('strategy.performance.roi-review', agreement.id), {}, { preserveScroll: true })
                                                }
                                            >
                                                Generate ROI review
                                            </Button>
                                        )}

                                        {agreement.quality_criteria !== null && Object.keys(agreement.quality_criteria).length > 0 && (
                                            <div className="overflow-x-auto rounded-lg border">
                                                <table className="w-full text-left text-sm">
                                                    <thead className="bg-muted/50 text-muted-foreground">
                                                        <tr>
                                                            <th className="p-3 font-medium">Quality criterion</th>
                                                            <th className="p-3 font-medium">Required</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y">
                                                        {Object.entries(agreement.quality_criteria).map(([criterion, expected]) => (
                                                            <tr key={criterion}>
                                                                <td className="p-3 font-medium">{humanize(criterion)}</td>
                                                                <td className="text-muted-foreground p-3 break-all">
                                                                    {typeof expected === 'object' ? JSON.stringify(expected) : String(expected)}
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}

                <div>
                    <h3 className="mb-2 text-sm font-medium">Replaced leads</h3>
                    {replacements.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No leads have been replaced under a guarantee.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Contact</th>
                                        <th className="p-3 font-medium">Reason</th>
                                        <th className="p-3 font-medium">Replaced</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {replacements.map((replacement) => (
                                        <tr key={replacement.id} className="hover:bg-muted/40 align-top">
                                            <td className="p-3 font-medium">Contact #{replacement.contact_id}</td>
                                            <td className="text-muted-foreground max-w-96 p-3">{replacement.reason}</td>
                                            <td className="text-muted-foreground p-3">{formatTime(replacement.replaced_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">ROI reviews</h3>
                    {reviews.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No reviews stored yet. Generate one on an agreement to snapshot attainment, revenue and spend for its period.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Agreement</th>
                                        <th className="p-3 font-medium">Period</th>
                                        <th className="p-3 text-center font-medium">Won revenue</th>
                                        <th className="p-3 text-center font-medium">Ad spend</th>
                                        <th className="p-3 text-center font-medium">ROI</th>
                                        <th className="p-3 font-medium">Targets</th>
                                        <th className="p-3 font-medium">Generated</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {reviews.map((review) => (
                                        <tr key={review.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{review.agreement ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">
                                                {review.period_start ?? '—'} → {review.period_end ?? '—'}
                                            </td>
                                            <td className="p-3 text-center">${(review.won_revenue / 100).toFixed(2)}</td>
                                            <td className="p-3 text-center">${(review.ad_spend / 100).toFixed(2)}</td>
                                            <td className="p-3 text-center">{review.roi !== null ? `${review.roi}x` : 'no spend recorded'}</td>
                                            <td className="p-3">
                                                {review.all_targets_met ? <Badge>met</Badge> : <Badge variant="destructive">missed</Badge>}
                                            </td>
                                            <td className="text-muted-foreground p-3">{formatTime(review.created_at)}</td>
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
