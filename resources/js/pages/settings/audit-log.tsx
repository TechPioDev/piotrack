import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Audit log', href: '/settings/audit-log' }];

type Actor = { name: string; email: string } | null;
type LogEntry = {
    id: string;
    action: string;
    actor: Actor;
    resource_type: string | null;
    resource_id: string | null;
    context: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
};
type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

type AuditLogProps = {
    logs: Paginated<LogEntry>;
    filters: { action?: string; actor?: string };
};

export default function AuditLog({ logs, filters }: AuditLogProps) {
    const [action, setAction] = useState(filters.action ?? '');
    const [actor, setActor] = useState(filters.actor ?? '');

    const applyFilters: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('audit.index'), { action, actor }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit log" />

            <SettingsLayout>
                <div className="space-y-4">
                    <HeadingSmall title="Audit log" description="A record of important actions taken in this organization" />

                    <form onSubmit={applyFilters} className="flex flex-wrap items-end gap-2">
                        <Input value={action} onChange={(e) => setAction(e.target.value)} placeholder="Filter by action…" className="w-52" />
                        <Input value={actor} onChange={(e) => setActor(e.target.value)} placeholder="Filter by person…" className="w-52" />
                        <Button type="submit" variant="outline">
                            Filter
                        </Button>
                    </form>

                    {logs.data.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No audit events match your filters yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Action</th>
                                        <th className="p-3">Person</th>
                                        <th className="p-3">Resource</th>
                                        <th className="p-3">When</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {logs.data.map((log) => (
                                        <tr key={log.id}>
                                            <td className="p-3 font-mono text-xs">{log.action}</td>
                                            <td className="p-3">{log.actor ? log.actor.name : '—'}</td>
                                            <td className="text-muted-foreground p-3">
                                                {log.resource_type ? `${log.resource_type} ${log.resource_id ?? ''}` : '—'}
                                            </td>
                                            <td className="text-muted-foreground p-3 whitespace-nowrap">
                                                {new Date(log.created_at).toLocaleString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {logs.links.length > 3 && (
                        <div className="flex flex-wrap gap-1">
                            {logs.links.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        className="text-muted-foreground px-3 py-1 text-sm"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ),
                            )}
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
