import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Integrations', href: '/settings/integrations' }];

type Connector = {
    key: string;
    name: string;
    category: string;
    auth_type: 'none' | 'api_key' | 'oauth';
    connectable: boolean;
    status: 'connected' | 'disconnected' | 'error';
    health: 'healthy' | 'degraded' | 'disconnected';
    last_synced_at: string | null;
    last_error: string | null;
    integration_id: number | null;
};

type SyncRun = {
    id: number;
    provider: string;
    provider_name: string;
    status: 'running' | 'success' | 'failed';
    records: number;
    error: string | null;
    started_at: string | null;
    finished_at: string | null;
};

function healthBadge(connector: Connector) {
    if (connector.status === 'connected') return <Badge variant="default">Connected</Badge>;
    if (connector.status === 'error') return <Badge variant="destructive">Error</Badge>;
    return <Badge variant="secondary">Not connected</Badge>;
}

function formatTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

function ConnectorCard({ connector, canManage }: { connector: Connector; canManage: boolean }) {
    const [showKey, setShowKey] = useState(false);
    const form = useForm<{ provider: string; api_key: string }>({ provider: connector.key, api_key: '' });

    const connect = () => {
        form.post(route('integrations.connect'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('api_key');
                setShowKey(false);
            },
        });
    };

    const post = (name: string) => {
        if (connector.integration_id) {
            router.post(route(name, connector.integration_id), {}, { preserveScroll: true });
        }
    };

    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-medium">{connector.name}</span>
                        {healthBadge(connector)}
                    </div>
                    <p className="text-muted-foreground mt-0.5 text-xs">
                        {connector.category} · {connector.auth_type === 'oauth' ? 'OAuth' : connector.auth_type === 'api_key' ? 'API key' : 'No auth'}
                    </p>
                </div>
            </div>

            {connector.status !== 'disconnected' && (
                <dl className="text-muted-foreground mt-3 space-y-0.5 text-xs">
                    <div className="flex justify-between">
                        <dt>Last synced</dt>
                        <dd>{formatTime(connector.last_synced_at)}</dd>
                    </div>
                    {connector.last_error && (
                        <div className="text-destructive flex justify-between gap-2">
                            <dt>Error</dt>
                            <dd className="text-right">{connector.last_error}</dd>
                        </div>
                    )}
                </dl>
            )}

            {/* Not yet connectable (OAuth connectors awaiting app registration). */}
            {!connector.connectable && <p className="text-muted-foreground mt-3 text-xs italic">Coming soon — requires OAuth setup.</p>}

            {canManage && connector.connectable && (
                <div className="mt-4 space-y-3">
                    {connector.status === 'disconnected' ? (
                        connector.auth_type === 'oauth' ? (
                            /* OAuth leaves the SPA for the provider's consent screen. */
                            <Button size="sm" asChild>
                                <a href={route('integrations.oauth.redirect', connector.key)}>Connect with OAuth</a>
                            </Button>
                        ) : showKey ? (
                            <div className="space-y-2">
                                <Label htmlFor={`key-${connector.key}`} className="text-xs">
                                    API key
                                </Label>
                                <Input
                                    id={`key-${connector.key}`}
                                    type="password"
                                    autoComplete="off"
                                    value={form.data.api_key}
                                    onChange={(e) => form.setData('api_key', e.target.value)}
                                    placeholder="Paste API key"
                                />
                                <InputError message={form.errors.api_key} />
                                <div className="flex gap-2">
                                    <Button size="sm" onClick={connect} disabled={form.processing}>
                                        Connect
                                    </Button>
                                    <Button size="sm" variant="ghost" onClick={() => setShowKey(false)}>
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <Button size="sm" onClick={() => setShowKey(true)}>
                                Connect
                            </Button>
                        )
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            <Button size="sm" onClick={() => post('integrations.sync')}>
                                Sync now
                            </Button>
                            {connector.status === 'error' && (
                                <Button size="sm" variant="secondary" onClick={() => post('integrations.reconnect')}>
                                    Reconnect
                                </Button>
                            )}
                            <Button size="sm" variant="ghost" className="text-red-600" onClick={() => post('integrations.disconnect')}>
                                Disconnect
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

type Webhook = {
    id: number;
    url: string;
    events: string[];
    is_active: boolean;
    failure_count: number;
    last_delivered_at: string | null;
    last_error: string | null;
};

function WebhooksSection({ webhooks, events, canManage }: { webhooks: Webhook[]; events: string[]; canManage: boolean }) {
    const form = useForm<{ url: string; events: string[] }>({ url: '', events: [] });

    const toggleEvent = (event: string) => {
        form.setData('events', form.data.events.includes(event) ? form.data.events.filter((e) => e !== event) : [...form.data.events, event]);
    };

    return (
        <div>
            <h3 className="mb-1 text-sm font-medium">Outbound webhooks</h3>
            <p className="text-muted-foreground mb-3 text-sm">
                POST signed JSON to any endpoint when things happen — the generic integration for Zapier and custom receivers. Deliveries carry an
                X-Piotrack-Signature (HMAC-SHA256 of the body with your signing secret). No events selected means all events.
            </p>

            {canManage && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('integrations.webhooks.store'), { preserveScroll: true, onSuccess: () => form.reset() });
                    }}
                    className="mb-4 space-y-2 rounded-lg border p-3"
                >
                    <div className="grid gap-1">
                        <Label htmlFor="wh-url">Endpoint URL (https)</Label>
                        <Input
                            id="wh-url"
                            placeholder="https://hooks.zapier.com/hooks/catch/…"
                            value={form.data.url}
                            onChange={(e) => form.setData('url', e.target.value)}
                        />
                        <InputError message={form.errors.url} />
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        {events.map((event) => (
                            <label key={event} className="flex items-center gap-1.5 text-sm">
                                <input type="checkbox" checked={form.data.events.includes(event)} onChange={() => toggleEvent(event)} />
                                <span className="font-mono text-xs">{event}</span>
                            </label>
                        ))}
                        <Button size="sm" type="submit" disabled={form.processing}>
                            Add webhook
                        </Button>
                    </div>
                </form>
            )}

            {webhooks.length === 0 ? (
                <p className="text-muted-foreground text-sm">No webhooks yet.</p>
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">URL</th>
                                <th className="p-3 font-medium">Events</th>
                                <th className="p-3 font-medium">Last delivery</th>
                                <th className="p-3 font-medium">Failures</th>
                                {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {webhooks.map((webhook) => (
                                <tr key={webhook.id}>
                                    <td className="p-3 font-medium break-all">{webhook.url}</td>
                                    <td className="p-3">
                                        {webhook.events.length === 0 ? (
                                            <Badge variant="secondary">all</Badge>
                                        ) : (
                                            <span className="font-mono text-xs">{webhook.events.join(', ')}</span>
                                        )}
                                    </td>
                                    <td className="text-muted-foreground p-3">
                                        {formatTime(webhook.last_delivered_at)}
                                        {webhook.last_error && <span className="text-destructive block text-xs">{webhook.last_error}</span>}
                                    </td>
                                    <td className="text-muted-foreground p-3">{webhook.failure_count}</td>
                                    {canManage && (
                                        <td className="p-3">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() =>
                                                        router.post(route('integrations.webhooks.test', webhook.id), {}, { preserveScroll: true })
                                                    }
                                                >
                                                    Test
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-destructive"
                                                    onClick={() =>
                                                        router.delete(route('integrations.webhooks.destroy', webhook.id), { preserveScroll: true })
                                                    }
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
    );
}

export default function Integrations({
    connectors,
    recentRuns,
    webhooks,
    webhookEvents,
}: {
    connectors: Connector[];
    recentRuns: SyncRun[];
    webhooks: Webhook[];
    webhookEvents: string[];
}) {
    const { can } = usePermissions();
    const canManage = can('integrations.manage');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrations" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Integrations" description="Connect third-party tools and sync data into piotrack" />

                    <div className="grid gap-3 sm:grid-cols-2">
                        {connectors.map((connector) => (
                            <ConnectorCard key={connector.key} connector={connector} canManage={canManage} />
                        ))}
                    </div>

                    <WebhooksSection webhooks={webhooks} events={webhookEvents} canManage={canManage} />

                    <div>
                        <h3 className="mb-2 text-sm font-medium">Recent syncs</h3>
                        {recentRuns.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No syncs yet. Connect a source and run a sync to see history here.</p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Connector</th>
                                            <th className="p-3 font-medium">Status</th>
                                            <th className="p-3 font-medium">Records</th>
                                            <th className="p-3 font-medium">Finished</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {recentRuns.map((run) => (
                                            <tr key={run.id}>
                                                <td className="p-3 font-medium">{run.provider_name}</td>
                                                <td className="p-3">
                                                    {run.status === 'success' ? (
                                                        <Badge variant="default">Success</Badge>
                                                    ) : run.status === 'failed' ? (
                                                        <Badge variant="destructive">Failed</Badge>
                                                    ) : (
                                                        <Badge variant="secondary">Running</Badge>
                                                    )}
                                                </td>
                                                <td className="text-muted-foreground p-3">{run.records}</td>
                                                <td className="text-muted-foreground p-3">{formatTime(run.finished_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
