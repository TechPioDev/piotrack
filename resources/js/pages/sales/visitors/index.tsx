import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { copyText } from '@/lib/clipboard';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Copy, Eye, Flame, UserCheck, Users } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales' },
    { title: 'Visitors', href: '/sales/visitors' },
];

type VisitorRow = {
    id: number;
    label: string;
    contact_id: number | null;
    company: string | null;
    identified_company: { company_name: string; domain: string; industry: string | null } | null;
    email: string | null;
    visits: number;
    page_views: number;
    intent_score: number;
    last_path: string | null;
    source: string | null;
    last_seen_at: string | null;
};
type Paginated = { data: VisitorRow[]; links: { url: string | null; label: string; active: boolean }[]; total: number };

function heat(score: number): { label: string; className: string } {
    if (score >= 16) return { label: 'Hot', className: 'text-red-600 dark:text-red-400' };
    if (score >= 6) return { label: 'Warm', className: 'text-amber-600 dark:text-amber-400' };
    return { label: 'Browsing', className: 'text-muted-foreground' };
}

export default function Visitors({
    visitors,
    enrichment_provider,
    snippet,
    identified,
    total,
}: {
    visitors: Paginated;
    enrichment_provider: string;
    trackingKey: string;
    snippet: string;
    identified: number;
    total: number;
}) {
    const [copied, setCopied] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Visitors" />
            <div className="space-y-4 p-4">
                <PageHeader
                    title="Visitors"
                    description="Who is on your website, what they care about, and how hot they run — from your own first-party data."
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Visitors tracked" value={total.toLocaleString('en-US')} icon={Users} />
                    <StatCard label="Identified" value={identified.toLocaleString('en-US')} icon={UserCheck} />
                    <StatCard label="Anonymous" value={(total - identified).toLocaleString('en-US')} icon={Eye} />
                </div>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 className="text-sm font-semibold">Install the tracker</h2>
                                <p className="text-muted-foreground text-sm">
                                    Paste this before <code className="bg-muted rounded px-1 text-xs">&lt;/body&gt;</code> on your website. Your
                                    hosted pages here already include it.
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={async () => {
                                    setCopied(await copyText(snippet));
                                    setTimeout(() => setCopied(false), 2000);
                                }}
                            >
                                <Copy className="size-3.5" aria-hidden /> {copied ? 'Copied!' : 'Copy snippet'}
                            </Button>
                        </div>
                        <pre className="bg-muted mt-3 overflow-x-auto rounded-lg p-3 text-xs">{snippet}</pre>
                    </CardContent>
                </Card>

                {visitors.data.length === 0 ? (
                    <EmptyState
                        icon={Eye}
                        title="No visitors yet"
                        description="Install the tracker on your website — visitors appear here the moment the first page loads."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <tr>
                                <TableHead>Visitor</TableHead>
                                <TableHead>Company</TableHead>
                                <TableHead className="text-center">Sessions</TableHead>
                                <TableHead className="text-center">Pages</TableHead>
                                <TableHead>Interest</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead>Last seen</TableHead>
                            </tr>
                        </TableHeader>
                        <TableBody>
                            {visitors.data.map((visitor) => {
                                const h = heat(visitor.intent_score);
                                return (
                                    <TableRow key={visitor.id}>
                                        <TableCell>
                                            {visitor.contact_id !== null ? (
                                                <Link
                                                    href={route('crm.contacts.show', visitor.contact_id)}
                                                    className="hover:text-brand-strong font-medium hover:underline"
                                                >
                                                    {visitor.label}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground">{visitor.label}</span>
                                            )}
                                            {visitor.email && visitor.contact_id === null && (
                                                <span className="text-muted-foreground block text-xs">{visitor.email}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {visitor.company ??
                                                (visitor.identified_company ? (
                                                    <span>
                                                        {visitor.identified_company.company_name}{' '}
                                                        <span className="text-xs">(reverse-IP · {enrichment_provider})</span>
                                                    </span>
                                                ) : (
                                                    '—'
                                                ))}
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">{visitor.visits}</TableCell>
                                        <TableCell className="text-center tabular-nums">{visitor.page_views}</TableCell>
                                        <TableCell>
                                            <span className={`inline-flex items-center gap-1 text-sm font-medium ${h.className}`}>
                                                {h.label === 'Hot' && <Flame className="size-3.5" aria-hidden />}
                                                {h.label}
                                                <span className="text-muted-foreground text-xs tabular-nums">({visitor.intent_score})</span>
                                            </span>
                                            {visitor.last_path && (
                                                <span className="text-muted-foreground block max-w-48 truncate text-xs">{visitor.last_path}</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {visitor.source ? (
                                                <Badge variant="outline">{visitor.source}</Badge>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{visitor.last_seen_at ?? '—'}</TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}

                {visitors.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {visitors.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`rounded px-3 py-1 text-sm transition-colors ${link.active ? 'bg-brand text-brand-foreground' : 'hover:bg-muted'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span key={i} className="text-muted-foreground px-3 py-1 text-sm" dangerouslySetInnerHTML={{ __html: link.label }} />
                            ),
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
