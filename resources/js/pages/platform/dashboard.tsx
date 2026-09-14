import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Platform', href: '/platform' }];

type Overview = {
    organizations: number;
    users: number;
    active_subscriptions: number;
    trialing: number;
    past_due: number;
    canceled: number;
    mrr: number;
    contacts: number;
    ai_requests: number;
    ai_spend: number;
};

type TenantUser = {
    id: number;
    name: string;
    email: string;
};

type Tenant = {
    id: number;
    name: string;
    slug: string;
    members: number;
    plan: string | null;
    status: string | null;
    trial_ends_at: string | null;
    created_at: string | null;
    /** Impersonatable members — platform staff are excluded server-side. */
    users: TenantUser[];
};

type ImpersonationRow = {
    id: number;
    impersonator: string | null;
    user: string | null;
    reason: string | null;
    started_at: string;
    ended_at: string | null;
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

/** `mrr` and `ai_spend` arrive in minor units. */
const money = (cents: number) => `$${(cents / 100).toFixed(2)}`;

function formatTime(iso: string | null): string {
    return iso === null ? '—' : new Date(iso).toLocaleString();
}

function statusVariant(status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'past_due' || status === 'canceled') return 'destructive';
    if (status === 'active') return 'default';
    if (status === 'trialing') return 'secondary';

    return 'outline';
}

/**
 * Starting a support session is a serious act, so the dialog states plainly what
 * it does before it can be submitted: the reason is mandatory (the server
 * enforces `min:5` as well), the session is logged, and the impersonated user
 * can see it.
 */
function ImpersonateDialog({ tenant }: { tenant: Tenant }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ user_id: string; reason: string }>({ user_id: '', reason: '' });
    // The service refuses some targets outright (platform staff); that comes back
    // under its own key rather than a field.
    const refusal = usePage<SharedData>().props.errors.impersonation;

    const reasonTooShort = form.data.reason.trim().length < 5;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (reasonTooShort || form.data.user_id === '') {
            return;
        }

        form.transform((data) => ({ reason: data.reason }));
        form.post(route('platform.impersonate.start', form.data.user_id), {
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
                    Impersonate
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Impersonate a user in {tenant.name}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="border-destructive/50 bg-destructive/10 text-destructive space-y-1 rounded-lg border p-3 text-sm">
                        <p className="font-medium">This is recorded support access, not a private view.</p>
                        <p>
                            You will act as this person: you will see their data and anything you do will be attributed to their account. The session,
                            your name and the reason below are written to the impersonation log, which is reviewable here and visible to the user. A
                            banner stays on every screen until you stop.
                        </p>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`impersonate_user_${tenant.id}`}>Member to act as</Label>
                        {/* Chosen by name, never typed as an id: mistyping one would
                            mean impersonating the wrong customer. */}
                        <Select value={form.data.user_id} onValueChange={(value) => form.setData('user_id', value)}>
                            <SelectTrigger id={`impersonate_user_${tenant.id}`}>
                                <SelectValue placeholder="Choose a member" />
                            </SelectTrigger>
                            <SelectContent>
                                {tenant.users.map((member) => (
                                    <SelectItem key={member.id} value={String(member.id)}>
                                        {member.name} — {member.email}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-xs">
                            {tenant.users.length === 0
                                ? `${tenant.name} has no impersonatable members.`
                                : 'Platform staff accounts are excluded and cannot be impersonated.'}
                        </p>
                        <InputError message={form.errors.user_id} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`impersonate_reason_${tenant.id}`}>Reason (required, at least 5 characters)</Label>
                        <textarea
                            id={`impersonate_reason_${tenant.id}`}
                            className={textareaClass}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            placeholder="e.g. Ticket #482 — reproducing the billing page error the customer reported"
                        />
                        {reasonTooShort && form.data.reason !== '' && (
                            <p className="text-muted-foreground text-xs">Write at least 5 characters describing why this access is needed.</p>
                        )}
                        <InputError message={form.errors.reason} />
                    </div>
                    <InputError message={refusal} />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" disabled={form.processing || reasonTooShort || form.data.user_id === ''}>
                            Start impersonating
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PlatformDashboard({
    overview,
    tenants,
    impersonations,
}: {
    overview: Overview;
    tenants: Tenant[];
    impersonations: ImpersonationRow[];
}) {
    const { can } = usePermissions();
    const canImpersonate = can('admin.impersonate');

    const counters: { label: string; value: string | number; note?: string }[] = [
        { label: 'Organizations', value: overview.organizations },
        { label: 'Users', value: overview.users },
        { label: 'Active subscriptions', value: overview.active_subscriptions },
        { label: 'Trialing', value: overview.trialing },
        { label: 'Past due', value: overview.past_due },
        { label: 'Canceled', value: overview.canceled },
        { label: 'MRR', value: money(overview.mrr), note: 'Annual plans normalized to a month' },
        { label: 'Contacts', value: overview.contacts },
        { label: 'AI requests', value: overview.ai_requests },
        { label: 'AI spend', value: money(overview.ai_spend), note: 'Estimated, all tenants' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Platform" />
            <div className="space-y-6 p-4">
                <Heading title="Platform console" description="Every tenant on this installation, the revenue they carry and who has accessed them" />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    {counters.map((counter) => (
                        <Card key={counter.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{counter.label}</p>
                                <p className="text-2xl font-semibold">{counter.value}</p>
                                {counter.note && <p className="text-muted-foreground text-xs">{counter.note}</p>}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Tenants</h3>
                    {tenants.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No organizations exist on this installation yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Organization</th>
                                        <th className="p-3">Slug</th>
                                        <th className="p-3 text-right">Members</th>
                                        <th className="p-3">Plan</th>
                                        <th className="p-3">Subscription</th>
                                        <th className="p-3">Trial ends</th>
                                        <th className="p-3">Created</th>
                                        {canImpersonate && <th className="p-3 text-right">Support access</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {tenants.map((tenant) => (
                                        <tr key={tenant.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{tenant.name}</td>
                                            <td className="text-muted-foreground p-3">{tenant.slug}</td>
                                            <td className="p-3 text-right">{tenant.members}</td>
                                            <td className="p-3">{tenant.plan ?? <span className="text-muted-foreground">No plan</span>}</td>
                                            <td className="p-3">
                                                {tenant.status === null ? (
                                                    <span className="text-muted-foreground">None</span>
                                                ) : (
                                                    <Badge variant={statusVariant(tenant.status)}>{tenant.status.replace(/_/g, ' ')}</Badge>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground p-3">{tenant.trial_ends_at ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{tenant.created_at ?? '—'}</td>
                                            {canImpersonate && (
                                                <td className="p-3">
                                                    <div className="flex justify-end">
                                                        <ImpersonateDialog tenant={tenant} />
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
                    <h3 className="mb-2 text-sm font-medium">Recent impersonations</h3>
                    <p className="text-muted-foreground mb-2 text-sm">
                        Every support session that has been opened into a customer account, with the reason given. A session with no end time is still
                        open right now.
                    </p>
                    {impersonations.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Nobody has impersonated a user. Sessions appear here as soon as one starts.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Impersonator</th>
                                        <th className="p-3">User</th>
                                        <th className="p-3">Reason</th>
                                        <th className="p-3">Started</th>
                                        <th className="p-3">Ended</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {impersonations.map((session) => (
                                        <tr key={session.id} className="hover:bg-muted/40 align-top">
                                            <td className="p-3 font-medium">{session.impersonator ?? 'Deleted account'}</td>
                                            <td className="p-3">{session.user ?? 'Deleted account'}</td>
                                            <td className="text-muted-foreground max-w-80 p-3">{session.reason ?? 'No reason recorded'}</td>
                                            <td className="text-muted-foreground p-3">{formatTime(session.started_at)}</td>
                                            <td className="p-3">
                                                {session.ended_at === null ? (
                                                    <Badge variant="destructive">Still open</Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">{formatTime(session.ended_at)}</span>
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
