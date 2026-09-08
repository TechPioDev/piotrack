import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'SEO', href: '/seo' },
    { title: 'Links', href: '/seo/links' },
];

type LinkRow = {
    source_domain: string;
    url: string;
    anchor: string;
    domain_authority: number;
    toxic: boolean;
    reasons: string[];
};

type VerifiedLink = { source: string; url: string | null; kind: string };

type Audit = {
    domain: string | null;
    provider: string;
    links: LinkRow[];
    referring_domains: number;
    avg_da: number | null;
    toxic: number;
    verified: VerifiedLink[];
};

type Gap = { competitor: string; source_domain: string; domain_authority: number; anchor: string };

export default function Links({ audit, gaps }: { audit: Audit; gaps: Gap[] }) {
    const { can } = usePermissions();
    const canManage = can('seo.keywords.manage');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Backlinks" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Backlink audit" description="Link profile, toxic-link flags, and the gaps competitors hold" />
                    {canManage && audit.toxic > 0 && (
                        <Button asChild variant="outline" size="sm">
                            <a href={route('seo.links.disavow')}>Download disavow.txt</a>
                        </Button>
                    )}
                </div>

                {audit.provider === 'fixture' && (
                    <p className="text-muted-foreground rounded-lg border border-dashed p-3 text-sm">
                        Link data below is <strong>simulated by the fixture driver</strong> — connect a live link index (Ahrefs / Google Search
                        Console) to replace it with real crawl data. Your verified first-party links are real regardless.
                    </p>
                )}

                {audit.domain === null ? (
                    <p className="text-muted-foreground text-sm">
                        Set your website URL on the brand profile (SEO → LLMO) — the audit needs to know which domain is yours.
                    </p>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {[
                                { label: 'Backlinks', value: audit.links.length },
                                { label: 'Referring domains', value: audit.referring_domains },
                                { label: 'Average DA', value: audit.avg_da ?? '—' },
                                { label: 'Flagged toxic', value: audit.toxic },
                            ].map((card) => (
                                <Card key={card.label}>
                                    <CardContent className="p-4">
                                        <p className="text-muted-foreground text-sm">{card.label}</p>
                                        <p className="text-2xl font-semibold">{card.value}</p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <div>
                            <h3 className="mb-2 text-sm font-medium">Link profile — {audit.domain}</h3>
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Source</th>
                                            <th className="p-3 font-medium">Anchor</th>
                                            <th className="p-3 text-center font-medium">DA</th>
                                            <th className="p-3 font-medium">Verdict</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {audit.links.map((link) => (
                                            <tr key={link.url} className="hover:bg-muted/40">
                                                <td className="p-3 font-medium">{link.source_domain}</td>
                                                <td className="text-muted-foreground p-3">{link.anchor}</td>
                                                <td className="p-3 text-center">{link.domain_authority}</td>
                                                <td className="p-3">
                                                    {link.toxic ? (
                                                        <span className="flex flex-wrap items-center gap-1">
                                                            <Badge variant="destructive">toxic</Badge>
                                                            <span className="text-muted-foreground text-xs">{link.reasons.join(', ')}</span>
                                                        </span>
                                                    ) : (
                                                        <Badge variant="secondary">clean</Badge>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </>
                )}

                <div>
                    <h3 className="mb-2 text-sm font-medium">Verified first-party links</h3>
                    <p className="text-muted-foreground mb-2 text-xs">
                        Placements your outreach actually won and citations you built — real records, independent of any link index.
                    </p>
                    {audit.verified.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No placements or citations recorded yet.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {audit.verified.map((link) => (
                                <li key={`${link.kind}:${link.source}:${link.url}`} className="flex flex-wrap items-center gap-2 p-2 text-sm">
                                    <Badge variant="outline">{link.kind}</Badge>
                                    <span className="font-medium">{link.source}</span>
                                    {link.url && (
                                        <a href={link.url} className="text-muted-foreground text-xs underline" target="_blank" rel="noreferrer">
                                            {link.url}
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Competitor link gaps</h3>
                    <p className="text-muted-foreground mb-2 text-xs">
                        Clean domains linking to a tracked competitor but not to you — each is one click from becoming an outreach prospect.
                    </p>
                    {gaps.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No gaps found — track competitors (with domains) under SEO to compare link profiles.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {gaps.map((gap) => (
                                <li key={gap.source_domain} className="flex flex-wrap items-center justify-between gap-2 p-2 text-sm">
                                    <span className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{gap.source_domain}</span>
                                        <Badge variant="outline">DA {gap.domain_authority}</Badge>
                                        <span className="text-muted-foreground text-xs">links to {gap.competitor}</span>
                                    </span>
                                    {canManage && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    route('seo.links.prospect'),
                                                    { source_domain: gap.source_domain, domain_authority: gap.domain_authority },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Add to outreach
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
