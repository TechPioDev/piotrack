import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Organization settings', href: '/settings/organization' }];

type Channel = {
    id: number;
    kind: string;
    url: string;
    has_secret: boolean;
    is_active: boolean;
};

type OrganizationProps = {
    organization: { id: number; name: string; slug: string };
    notification_channels: Channel[];
    channel_kinds: string[];
};

export default function OrganizationSettings({ organization, notification_channels, channel_kinds }: OrganizationProps) {
    const { can } = usePermissions();
    const form = useForm({ name: organization.name });
    const del = useForm({ name: '' });
    const channelForm = useForm<{ kind: string; url: string; secret: string }>({ kind: 'slack', url: '', secret: '' });

    const addChannel: FormEventHandler = (e) => {
        e.preventDefault();
        channelForm.transform((data) => ({ ...data, secret: data.secret || null }));
        channelForm.post(route('organization.channels.store'), {
            preserveScroll: true,
            onSuccess: () => channelForm.reset(),
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('organization.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Organization" description="Update your organization's profile" />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Organization name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                disabled={!can('organization.update')}
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        {can('organization.update') && (
                            <div className="flex items-center gap-4">
                                <Button disabled={form.processing}>Save</Button>
                                <Transition
                                    show={form.recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-neutral-600">Saved</p>
                                </Transition>
                            </div>
                        )}
                    </form>
                </div>

                {can('organization.update') && (
                    <div className="mt-10 space-y-4">
                        <HeadingSmall
                            title="Notification channels"
                            description="Organization alerts also post to these — a Slack or Teams incoming-webhook URL, or a signed webhook to your own endpoint"
                        />
                        {notification_channels.length > 0 && (
                            <ul className="divide-y rounded-lg border">
                                {notification_channels.map((channel) => (
                                    <li key={channel.id} className="flex items-center justify-between gap-3 p-3 text-sm">
                                        <div className="flex min-w-0 items-center gap-2">
                                            <Badge variant="outline">{channel.kind}</Badge>
                                            <span className="text-muted-foreground truncate">{channel.url}</span>
                                            {channel.has_secret && <Badge variant="secondary">signed</Badge>}
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={() =>
                                                router.delete(route('organization.channels.destroy', channel.id), { preserveScroll: true })
                                            }
                                        >
                                            Remove
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <form onSubmit={addChannel} className="flex flex-wrap items-end gap-2">
                            <div className="grid gap-1">
                                <Label htmlFor="channel_kind">Kind</Label>
                                <Select value={channelForm.data.kind} onValueChange={(v) => channelForm.setData('kind', v)}>
                                    <SelectTrigger id="channel_kind" className="w-28">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {channel_kinds.map((kind) => (
                                            <SelectItem key={kind} value={kind}>
                                                {kind}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid min-w-64 flex-1 gap-1">
                                <Label htmlFor="channel_url">Webhook URL (https)</Label>
                                <Input
                                    id="channel_url"
                                    type="url"
                                    value={channelForm.data.url}
                                    onChange={(e) => channelForm.setData('url', e.target.value)}
                                    placeholder="https://hooks.slack.com/services/…"
                                />
                                <InputError message={channelForm.errors.url} />
                            </div>
                            {channelForm.data.kind === 'webhook' && (
                                <div className="grid gap-1">
                                    <Label htmlFor="channel_secret">Signing secret (optional)</Label>
                                    <Input
                                        id="channel_secret"
                                        value={channelForm.data.secret}
                                        onChange={(e) => channelForm.setData('secret', e.target.value)}
                                    />
                                </div>
                            )}
                            <Button type="submit" disabled={channelForm.processing || channelForm.data.url === ''}>
                                Add channel
                            </Button>
                        </form>
                    </div>
                )}

                {can('organization.delete') && (
                    <div className="mt-10 space-y-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-950/20">
                        <HeadingSmall title="Delete organization" description="This permanently removes the organization and all of its data" />

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">Delete organization</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Delete {organization.name}?</DialogTitle>
                                <DialogDescription>
                                    This cannot be undone. All members lose access. Type the organization name <strong>{organization.name}</strong> to
                                    confirm.
                                </DialogDescription>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        del.delete(route('organization.destroy'), { preserveScroll: true });
                                    }}
                                    className="space-y-4"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="confirm_name" className="sr-only">
                                            Organization name
                                        </Label>
                                        <Input
                                            id="confirm_name"
                                            value={del.data.name}
                                            onChange={(e) => del.setData('name', e.target.value)}
                                            placeholder={organization.name}
                                            autoComplete="off"
                                        />
                                        <InputError message={del.errors.name} />
                                    </div>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button type="button" variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button type="submit" variant="destructive" disabled={del.processing}>
                                            Delete organization
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                )}
            </SettingsLayout>
        </AppLayout>
    );
}
