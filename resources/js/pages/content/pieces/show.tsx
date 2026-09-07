import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Piece = {
    id: number;
    title: string;
    content_type: string;
    funnel_stage: string | null;
    status: string;
    excerpt: string | null;
    body: string | null;
    target_keyword: string | null;
    url: string | null;
    cta: string | null;
    is_lead_magnet: boolean;
    optimization_score: number;
    published_at: string | null;
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function statusVariant(status: string): 'default' | 'secondary' {
    return status === 'published' ? 'default' : 'secondary';
}

export default function ContentPieceShow({ piece, statuses }: { piece: Piece; statuses: string[] }) {
    const { can } = usePermissions();
    const canManage = can('content.pieces.manage');

    const form = useForm<{
        title: string;
        content_type: string;
        excerpt: string;
        body: string;
        target_keyword: string;
        url: string;
        cta: string;
        is_lead_magnet: boolean;
    }>({
        title: piece.title,
        content_type: piece.content_type,
        excerpt: piece.excerpt ?? '',
        body: piece.body ?? '',
        target_keyword: piece.target_keyword ?? '',
        url: piece.url ?? '',
        cta: piece.cta ?? '',
        is_lead_magnet: piece.is_lead_magnet,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Content', href: '/content/pieces' },
        { title: piece.title, href: `/content/pieces/${piece.id}` },
    ];

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('content.pieces.update', piece.id), { preserveScroll: true });
    };

    const setStatus = (status: string) => router.post(route('content.pieces.status', piece.id), { status }, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={piece.title} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-2">
                        <Heading title={piece.title} description={piece.funnel_stage ?? undefined} />
                        <Badge variant="outline">{piece.content_type}</Badge>
                        <Badge variant={statusVariant(piece.status)}>{piece.status}</Badge>
                    </div>
                    <Card>
                        <CardContent className="p-4 text-right">
                            <p className="text-3xl font-bold">{piece.optimization_score}/100</p>
                            <p className="text-muted-foreground text-xs">Optimization score</p>
                        </CardContent>
                    </Card>
                </div>

                {canManage && (
                    <div className="space-y-2">
                        <h3 className="text-sm font-medium">Workflow</h3>
                        <p className="text-muted-foreground text-xs">Editorial flow: idea → draft → in review → approved → published → archived.</p>
                        <div className="flex flex-wrap gap-2">
                            {statuses.map((status) => (
                                <Button
                                    key={status}
                                    size="sm"
                                    variant="secondary"
                                    disabled={status === piece.status}
                                    onClick={() => setStatus(status)}
                                >
                                    {status}
                                </Button>
                            ))}
                        </div>
                        {['webinar', 'video', 'podcast', 'interview'].includes(piece.content_type) && (
                            <div className="flex flex-wrap items-center gap-2 pt-1">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => router.post(route('content.pieces.promote', piece.id), {}, { preserveScroll: true })}
                                >
                                    Promote across networks
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => router.post(route('content.pieces.clips', piece.id), { count: 3 }, { preserveScroll: true })}
                                >
                                    Schedule 3 clips
                                </Button>
                                <p className="text-muted-foreground text-xs">
                                    Announcements per network, and clip slots to attach your cuts to — all editable under Content → Social.
                                </p>
                            </div>
                        )}
                        {can('ads.campaigns.manage') && (
                            <div className="flex flex-wrap items-center gap-2 pt-1">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(route('ads.linkedin.promote-content'), { content_piece_id: piece.id }, { preserveScroll: true })
                                    }
                                >
                                    Promote on LinkedIn
                                </Button>
                                <p className="text-muted-foreground text-xs">
                                    Creates a draft sponsored-content campaign with creative from this piece
                                    {piece.content_type === 'case_study' ? ' — case studies promote for conversions.' : '.'}
                                </p>
                            </div>
                        )}
                    </div>
                )}

                <Card>
                    <CardContent className="p-4">
                        <form onSubmit={save} className="space-y-3">
                            <div className="grid gap-1">
                                <Label htmlFor="title">Title</Label>
                                <Input id="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                                <InputError message={form.errors.title} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="excerpt">Excerpt</Label>
                                <textarea
                                    id="excerpt"
                                    className={textareaClass}
                                    value={form.data.excerpt}
                                    onChange={(e) => form.setData('excerpt', e.target.value)}
                                />
                                <InputError message={form.errors.excerpt} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="body">Body</Label>
                                <textarea
                                    id="body"
                                    className={`${textareaClass} min-h-64`}
                                    value={form.data.body}
                                    onChange={(e) => form.setData('body', e.target.value)}
                                />
                                <InputError message={form.errors.body} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="target_keyword">Target keyword</Label>
                                    <Input
                                        id="target_keyword"
                                        value={form.data.target_keyword}
                                        onChange={(e) => form.setData('target_keyword', e.target.value)}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="cta">CTA</Label>
                                    <Input id="cta" value={form.data.cta} onChange={(e) => form.setData('cta', e.target.value)} />
                                </div>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="url">URL</Label>
                                <Input id="url" type="url" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} />
                                <InputError message={form.errors.url} />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={form.data.is_lead_magnet} onCheckedChange={(v) => form.setData('is_lead_magnet', v === true)} />
                                Lead magnet
                            </label>
                            {canManage && (
                                <Button type="submit" disabled={form.processing}>
                                    Save
                                </Button>
                            )}
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
