import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';

type Section = { key: string; label: string; ok: boolean; items: string[] };

type PageRow = {
    url: string;
    status: number | null;
    depth: number;
    title: string;
    inlinks: number;
    outlinks: number;
    bytes: number;
};

type Props = {
    crawl: {
        id: number;
        start_url: string;
        pages_crawled: number;
        issues_count: number;
        report: { sections: Section[]; pages: PageRow[]; depths: Record<string, number> };
        created_at: string | null;
    };
};

function statusBadge(status: number | null) {
    if (status === null) return <Badge variant="destructive">unfetchable</Badge>;
    if (status >= 400) return <Badge variant="destructive">{status}</Badge>;
    if (status >= 300) return <Badge variant="secondary">{status}</Badge>;
    return <Badge variant="outline">{status}</Badge>;
}

export default function CrawlReport({ crawl }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'SEO Audits', href: '/seo/audits' },
        { title: 'Site crawl', href: `/seo/audits/crawl/${crawl.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Site crawl" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Site crawl report"
                    description={`${crawl.start_url} — ${crawl.pages_crawled} pages, ${crawl.issues_count} findings`}
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    {crawl.report.sections.map((section) => (
                        <Card key={section.key}>
                            <CardContent className="p-4">
                                <div className="mb-2 flex items-center gap-2">
                                    {section.ok ? (
                                        <CheckCircle2 className="size-4 text-green-600" />
                                    ) : (
                                        <XCircle className="text-destructive size-4" />
                                    )}
                                    <h3 className="text-sm font-medium">{section.label}</h3>
                                    {!section.ok && <Badge variant="secondary">{section.items.length}</Badge>}
                                </div>
                                {section.ok ? (
                                    <p className="text-muted-foreground text-sm">No issues found.</p>
                                ) : (
                                    <ul className="text-muted-foreground list-disc space-y-1 pl-5 text-sm">
                                        {section.items.map((item) => (
                                            <li key={item} className="break-all">
                                                {item}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Crawled pages</h3>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">URL</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 text-center font-medium">Depth</th>
                                    <th className="p-3 text-center font-medium">Inlinks</th>
                                    <th className="p-3 text-center font-medium">Outlinks</th>
                                    <th className="p-3 text-right font-medium">HTML KB</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {crawl.report.pages.map((page) => (
                                    <tr key={page.url} className="hover:bg-muted/40">
                                        <td className="max-w-md p-3 break-all">{page.url}</td>
                                        <td className="p-3">{statusBadge(page.status)}</td>
                                        <td className="p-3 text-center">{page.depth}</td>
                                        <td className="p-3 text-center">{page.inlinks}</td>
                                        <td className="p-3 text-center">{page.outlinks}</td>
                                        <td className="p-3 text-right">{Math.round(page.bytes / 1024)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="text-muted-foreground mt-2 text-xs">
                        Bounded crawl (up to 20 pages, same host). Core Web Vitals need field data and are not part of this report.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
