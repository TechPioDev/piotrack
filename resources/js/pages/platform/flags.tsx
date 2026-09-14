import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { OctagonX } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Feature Flags', href: '/platform/flags' }];

type Rollout = {
    percentage?: number | null;
    organizations?: number[] | null;
} | null;

type Flag = {
    id: number;
    key: string;
    description: string | null;
    is_enabled: boolean;
    is_kill_switch: boolean;
    rollout: Rollout;
};

type FlagForm = {
    key: string;
    description: string;
    is_enabled: boolean;
    is_kill_switch: boolean;
    percentage: string;
    organizations: string;
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

/** Comma-separated organization ids -> a clean numeric list. */
function parseOrganizations(value: string): number[] {
    return value
        .split(',')
        .map((part) => Number(part.trim()))
        .filter((id) => Number.isInteger(id) && id > 0);
}

function describeRollout(flag: Flag): string {
    if (flag.is_kill_switch) {
        return 'Kill switch — targeting ignored';
    }

    const percentage = flag.rollout?.percentage ?? null;
    const organizations = flag.rollout?.organizations ?? [];

    const parts: string[] = [];

    if (percentage !== null) parts.push(`${percentage}% of organizations`);
    if (organizations.length > 0) parts.push(`${organizations.length} targeted org${organizations.length === 1 ? '' : 's'}`);

    return parts.length === 0 ? 'Everyone (no rollout limit)' : parts.join(' · ');
}

function FlagDialog({ flag, trigger }: { flag?: Flag; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const form = useForm<FlagForm>({
        key: flag?.key ?? '',
        description: flag?.description ?? '',
        is_enabled: flag?.is_enabled ?? false,
        is_kill_switch: flag?.is_kill_switch ?? false,
        percentage: flag?.rollout?.percentage != null ? String(flag.rollout.percentage) : '',
        organizations: (flag?.rollout?.organizations ?? []).join(', '),
    });

    // The rollout is validated as a nested array server-side, so its errors come
    // back under dotted keys rather than the flat form fields used here.
    const { errors } = usePage<SharedData>().props;
    const id = flag?.id ?? 'new';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            key: data.key,
            description: data.description === '' ? null : data.description,
            is_enabled: data.is_enabled,
            is_kill_switch: data.is_kill_switch,
            rollout: {
                percentage: data.percentage === '' ? null : Number(data.percentage),
                organizations: parseOrganizations(data.organizations),
            },
        }));
        form.post(route('platform.flags.save'), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{flag ? `Edit ${flag.key}` : 'New feature flag'}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`flag_key_${id}`}>Key</Label>
                        <Input
                            id={`flag_key_${id}`}
                            value={form.data.key}
                            onChange={(e) => form.setData('key', e.target.value)}
                            readOnly={flag !== undefined}
                            placeholder="ai.agent.autopilot"
                        />
                        <InputError message={form.errors.key} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`flag_description_${id}`}>Description</Label>
                        <textarea
                            id={`flag_description_${id}`}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="What this flag turns on, and who asked for it."
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <label className="flex items-start gap-3 rounded-lg border p-3" htmlFor={`flag_enabled_${id}`}>
                        <Checkbox
                            id={`flag_enabled_${id}`}
                            checked={form.data.is_enabled}
                            onCheckedChange={(v) => form.setData('is_enabled', v === true)}
                        />
                        <span className="text-sm">
                            <span className="font-medium">Enabled</span>
                            <span className="text-muted-foreground block">Off means nobody gets the feature, whatever the rollout below says.</span>
                        </span>
                    </label>

                    <label
                        className="border-destructive bg-destructive/10 flex items-start gap-3 rounded-lg border-2 p-3"
                        htmlFor={`flag_kill_${id}`}
                    >
                        <Checkbox
                            id={`flag_kill_${id}`}
                            checked={form.data.is_kill_switch}
                            onCheckedChange={(v) => form.setData('is_kill_switch', v === true)}
                        />
                        <span className="text-sm">
                            <span className="text-destructive flex items-center gap-1 font-semibold">
                                <OctagonX className="size-4" aria-hidden="true" />
                                Kill switch
                            </span>
                            <span className="text-destructive/90 block">
                                Overrides all targeting. While this is on the feature is off for every organization — the percentage and the targeted
                                org list below are ignored entirely.
                            </span>
                        </span>
                    </label>

                    <fieldset className="space-y-3 rounded-lg border p-3" disabled={form.data.is_kill_switch}>
                        <legend className="px-1 text-sm font-medium">Rollout</legend>
                        {form.data.is_kill_switch && (
                            <p className="text-muted-foreground text-xs">Disabled while the kill switch is on — it overrides all targeting.</p>
                        )}
                        <div className="grid gap-1">
                            <Label htmlFor={`flag_percentage_${id}`}>Percentage of organizations</Label>
                            <Input
                                id={`flag_percentage_${id}`}
                                type="number"
                                min="0"
                                max="100"
                                value={form.data.percentage}
                                onChange={(e) => form.setData('percentage', e.target.value)}
                                placeholder="Blank means no percentage limit"
                            />
                            <InputError message={errors['rollout.percentage']} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`flag_orgs_${id}`}>Targeted organization ids</Label>
                            <Input
                                id={`flag_orgs_${id}`}
                                value={form.data.organizations}
                                onChange={(e) => form.setData('organizations', e.target.value)}
                                placeholder="12, 34, 56"
                            />
                            <p className="text-muted-foreground text-xs">
                                Comma-separated. These organizations get the feature regardless of the percentage.
                            </p>
                            <InputError message={errors['rollout.organizations']} />
                        </div>
                    </fieldset>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save flag
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PlatformFlags({ flags }: { flags: Flag[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Feature Flags" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Feature flags" description="Platform-wide switches, resolved per organization at request time" />
                    <FlagDialog trigger={<Button>New flag</Button>} />
                </div>

                <Card>
                    <CardContent className="text-muted-foreground p-4 text-sm">
                        A flag reaches an organization only when it is <span className="text-foreground font-medium">enabled</span> and the
                        organization falls inside the rollout. A{' '}
                        <span className="text-destructive font-medium">kill switch overrides everything</span> — it turns the feature off for every
                        tenant no matter what the rollout says.
                    </CardContent>
                </Card>

                {flags.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No feature flags defined yet.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3">Key</th>
                                    <th className="p-3">Description</th>
                                    <th className="p-3">Enabled</th>
                                    <th className="p-3">Kill switch</th>
                                    <th className="p-3">Rollout</th>
                                    <th className="p-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {flags.map((flag) => (
                                    <tr key={flag.id} className={flag.is_kill_switch ? 'bg-destructive/10 align-top' : 'hover:bg-muted/40 align-top'}>
                                        <td className="p-3 font-medium">{flag.key}</td>
                                        <td className="text-muted-foreground max-w-80 p-3">{flag.description ?? '—'}</td>
                                        <td className="p-3">
                                            {flag.is_enabled ? <Badge>Enabled</Badge> : <Badge variant="secondary">Disabled</Badge>}
                                        </td>
                                        <td className="p-3">
                                            {flag.is_kill_switch ? (
                                                <Badge variant="destructive" className="gap-1">
                                                    <OctagonX className="size-3" aria-hidden="true" />
                                                    Killed
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        <td className="p-3">
                                            <p className={flag.is_kill_switch ? 'text-destructive font-medium' : ''}>{describeRollout(flag)}</p>
                                            {!flag.is_kill_switch && (flag.rollout?.organizations ?? []).length > 0 && (
                                                <p className="text-muted-foreground text-xs">
                                                    Org ids: {(flag.rollout?.organizations ?? []).join(', ')}
                                                </p>
                                            )}
                                        </td>
                                        <td className="p-3">
                                            <div className="flex justify-end">
                                                <FlagDialog
                                                    flag={flag}
                                                    trigger={
                                                        <Button size="sm" variant="outline">
                                                            Edit
                                                        </Button>
                                                    }
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
