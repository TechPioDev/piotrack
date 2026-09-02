import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'SEO Audits', href: '/seo/audits' }];

type Audit = {
    id: number;
    url: string;
    score: number;
    issues_count: number;
    created_at: string | null;
};

type Crawl = {
    id: number;
    start_url: string;
    pages_crawled: number;
    issues_count: number;
    created_at: string | null;
};

function scoreVariant(score: number): 'default' | 'secondary' | 'destructive' {
    if (score >= 80) return 'default';
    if (score >= 50) return 'secondary';
    return 'destructive';
}

function formatTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

export default function SeoAudits({ audits, crawls }: { audits: Audit[]; crawls: Crawl[] }) {
    const { can } = usePermissions();
    const canManage = can('seo.audits.manage');
    const form = useForm<{ url: string }>({ url: '' });
    const crawlForm = useForm<{ url: string }>({ url: '' });

    const run: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('seo.audits.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const runCrawl: FormEventHandler = (e) => {
        e.preventDefault();
        crawlForm.post(route('seo.audits.crawl'), { preserveScroll: true, onSuccess: () => crawlForm.reset() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SEO Audits" />
            <div className="space-y-4 p-4">
                <Heading title="Technical audits" description={`${audits.length} total`} />

                {canManage && (
                    <form onSubmit={run} className="flex flex-wrap items-start gap-2">
                        <div className="grid flex-1 gap-1">
                            <Input
                                type="url"
                                placeholder="https://example.com"
                                value={form.data.url}
                                onChange={(e) => form.setData('url', e.target.value)}
                            />
                            <InputError message={form.errors.url} />
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            Run technical audit
                        </Button>
                    </form>
                )}

                {canManage && (
                    <form onSubmit={runCrawl} className="flex flex-wrap items-start gap-2">
                        <div className="grid flex-1 gap-1">
                            <Input
                                type="url"
                                placeholder="https://example.com — crawl the whole site (up to 20 pages)"
                                value={crawlForm.data.url}
                                onChange={(e) => crawlForm.setData('url', e.target.value)}
                            />
                            <InputError message={crawlForm.errors.url} />
                        </div>
                        <Button type="submit" variant="outline" disabled={crawlForm.processing}>
                            Crawl site
                        </Button>
                    </form>
                )}

                {crawls.length > 0 && (
                    <div>
                        <h3 className="mb-2 text-sm font-medium">Site crawls</h3>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Start URL</th>
                                        <th className="p-3 text-center font-medium">Pages</th>
                                        <th className="p-3 text-center font-medium">Findings</th>
                                        <th className="p-3 font-medium">Created</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {crawls.map((crawl) => (
                                        <tr key={crawl.id} className="hover:bg-muted/40">
                                            <td className="p-3">
                                                <Link
                                                    href={route('seo.audits.crawl.show', crawl.id)}
                                                    className="font-medium break-all hover:underline"
                                                >
                                                    {crawl.start_url}
                                                </Link>
                                            </td>
                                            <td className="p-3 text-center">{crawl.pages_crawled}</td>
                                            <td className="p-3 text-center">{crawl.issues_count}</td>
                                            <td className="text-muted-foreground p-3">{formatTime(crawl.created_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {audits.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No audits yet. Run a technical audit to check a page.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">URL</th>
                                    <th className="p-3 font-medium">Score</th>
                                    <th className="p-3 text-center font-medium">Issues</th>
                                    <th className="p-3 font-medium">Created</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {audits.map((audit) => (
                                    <tr key={audit.id} className="hover:bg-muted/40">
                                        <td className="p-3">
                                            <Link href={route('seo.audits.show', audit.id)} className="font-medium hover:underline">
                                                {audit.url}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant={scoreVariant(audit.score)}>{audit.score}</Badge>
                                        </td>
                                        <td className="p-3 text-center">{audit.issues_count}</td>
                                        <td className="text-muted-foreground p-3">{formatTime(audit.created_at)}</td>
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
