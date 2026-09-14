import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Approvals', href: '/ai/actions' }];

type AiAction = {
    id: number;
    type: string;
    summary: string | null;
    payload: Record<string, unknown> | null;
    status: string;
    sensitive: boolean;
    result: string | null;
    confirmed_at: string | null;
    executed_at: string | null;
    created_at: string | null;
};

function formatTime(iso: string | null): string {
    return iso === null ? '—' : new Date(iso).toLocaleString();
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'rejected') return 'destructive';
    if (status === 'executed' || status === 'confirmed') return 'default';
    return 'secondary';
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'boolean') return value ? 'true' : 'false';
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}

/**
 * The payload is what the approver is actually authorising, so it is rendered in
 * full — a readable field list plus the raw JSON — rather than summarised away.
 */
function PayloadDetail({ payload }: { payload: Record<string, unknown> | null }) {
    const entries = Object.entries(payload ?? {});

    if (entries.length === 0) {
        return <p className="text-muted-foreground text-sm">This proposal carries no payload.</p>;
    }

    return (
        <div className="space-y-3">
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-left text-sm">
                    <thead className="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th className="p-3">Field</th>
                            <th className="p-3">Proposed value</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {entries.map(([field, value]) => (
                            <tr key={field}>
                                <td className="p-3 font-medium">{field}</td>
                                <td className="p-3 break-all">{formatValue(value)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <details>
                <summary className="text-muted-foreground cursor-pointer text-sm">Raw payload</summary>
                <pre className="bg-muted mt-2 overflow-x-auto rounded-lg p-3 text-xs">{JSON.stringify(payload, null, 2)}</pre>
            </details>
        </div>
    );
}

function PendingAction({ action, canApprove }: { action: AiAction; canApprove: boolean }) {
    const approve = () => router.post(route('ai.actions.approve', action.id), {}, { preserveScroll: true });
    const reject = () => router.post(route('ai.actions.reject', action.id), {}, { preserveScroll: true });

    return (
        <Card>
            <CardContent className="space-y-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">{action.type}</Badge>
                            {action.sensitive && (
                                <Badge variant="destructive" className="gap-1">
                                    <ShieldAlert className="h-3 w-3" />
                                    Sensitive
                                </Badge>
                            )}
                            <Badge variant="secondary">Awaiting approval</Badge>
                        </div>
                        <p className="font-medium">{action.summary ?? 'No summary was recorded for this proposal.'}</p>
                        <p className="text-muted-foreground text-sm">Proposed {formatTime(action.created_at)}</p>
                    </div>
                </div>

                <PayloadDetail payload={action.payload} />

                {canApprove ? (
                    <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                        <p className="text-muted-foreground mr-auto text-sm">
                            Approving applies this change immediately. Read the payload above before choosing.
                        </p>
                        <Button variant="outline" onClick={reject}>
                            Reject
                        </Button>
                        <Button variant="outline" onClick={approve}>
                            Approve
                        </Button>
                    </div>
                ) : (
                    <p className="text-muted-foreground border-t pt-3 text-sm">
                        You can review this proposal but not confirm it. Approving requires the <code>ai.actions.approve</code> permission.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

export default function AiActions({ actions }: { actions: AiAction[] }) {
    const { can } = usePermissions();
    const canApprove = can('ai.actions.approve');

    const pending = actions.filter((action) => action.status === 'pending');
    const decided = actions.filter((action) => action.status !== 'pending');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Approvals" />
            <div className="space-y-6 p-4">
                <Heading
                    title="AI approvals"
                    description="Nothing the AI proposes is applied until a person confirms it here. Read the full payload before deciding."
                />

                <div>
                    <h3 className="mb-2 text-sm font-medium">Awaiting approval ({pending.length})</h3>
                    {pending.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            Nothing is waiting for a decision. Proposals appear here before they take effect.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {pending.map((action) => (
                                <PendingAction key={action.id} action={action} canApprove={canApprove} />
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Decided</h3>
                    {decided.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No proposal has been decided yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Type</th>
                                        <th className="p-3">Summary</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3">Result</th>
                                        <th className="p-3">Confirmed</th>
                                        <th className="p-3">Executed</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {decided.map((action) => (
                                        <tr key={action.id} className="hover:bg-muted/40 align-top">
                                            <td className="p-3">
                                                <div className="flex flex-wrap items-center gap-1">
                                                    <span className="font-medium">{action.type}</span>
                                                    {action.sensitive && (
                                                        <Badge variant="destructive" className="gap-1">
                                                            <ShieldAlert className="h-3 w-3" />
                                                            Sensitive
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="text-muted-foreground max-w-80 p-3">
                                                <p>{action.summary ?? '—'}</p>
                                                <details className="mt-1">
                                                    <summary className="cursor-pointer text-xs">Payload</summary>
                                                    <pre className="bg-muted mt-1 overflow-x-auto rounded-lg p-2 text-xs">
                                                        {JSON.stringify(action.payload ?? {}, null, 2)}
                                                    </pre>
                                                </details>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant={statusVariant(action.status)}>{action.status}</Badge>
                                            </td>
                                            <td className="text-muted-foreground max-w-64 p-3 break-words">{action.result ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{formatTime(action.confirmed_at)}</td>
                                            <td className="text-muted-foreground p-3">{formatTime(action.executed_at)}</td>
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
