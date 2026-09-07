import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Content', href: '/content/pieces' }];

type Piece = {
    id: number;
    title: string;
    content_type: string;
    funnel_stage: string | null;
    status: string;
    is_lead_magnet: boolean;
    optimization_score: number;
};

type FunnelStage = 'tof' | 'mof' | 'bof';

const FUNNEL_STAGES: FunnelStage[] = ['tof', 'mof', 'bof'];

function statusVariant(status: string): 'default' | 'secondary' {
    return status === 'published' ? 'default' : 'secondary';
}

export default function ContentPieces({ pieces, types, verticals }: { pieces: Piece[]; types: string[]; verticals: { id: number; name: string }[] }) {
    const { can } = usePermissions();
    const canManage = can('content.pieces.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{
        title: string;
        content_type: string;
        funnel_stage: string;
        target_keyword: string;
        cta: string;
        is_lead_magnet: boolean;
        vertical_id: string;
    }>({
        title: '',
        content_type: types[0] ?? '',
        funnel_stage: '',
        target_keyword: '',
        cta: '',
        is_lead_magnet: false,
        vertical_id: '',
    });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, vertical_id: data.vertical_id === '' ? null : Number(data.vertical_id) }));
        form.post(route('content.pieces.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Content" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Content" description={`${pieces.length} total`} />
                    {canManage && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>New content</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>New content</DialogTitle>
                                <form onSubmit={create} className="space-y-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="title">Title</Label>
                                        <Input id="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                                        <InputError message={form.errors.title} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="content_type">Type</Label>
                                            <Select value={form.data.content_type} onValueChange={(v) => form.setData('content_type', v)}>
                                                <SelectTrigger id="content_type">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {types.map((type) => (
                                                        <SelectItem key={type} value={type}>
                                                            {type}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={form.errors.content_type} />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="funnel_stage">Funnel stage</Label>
                                            <Select value={form.data.funnel_stage} onValueChange={(v) => form.setData('funnel_stage', v)}>
                                                <SelectTrigger id="funnel_stage">
                                                    <SelectValue placeholder="Select a stage" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {FUNNEL_STAGES.map((stage) => (
                                                        <SelectItem key={stage} value={stage}>
                                                            {stage}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={form.errors.funnel_stage} />
                                        </div>
                                    </div>
                                    {verticals.length > 0 && (
                                        <div className="grid gap-1">
                                            <Label htmlFor="piece_vertical">Vertical (optional)</Label>
                                            <Select value={form.data.vertical_id} onValueChange={(v) => form.setData('vertical_id', v)}>
                                                <SelectTrigger id="piece_vertical">
                                                    <SelectValue placeholder="Not bound to one vertical" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {verticals.map((vertical) => (
                                                        <SelectItem key={vertical.id} value={String(vertical.id)}>
                                                            {vertical.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={form.errors.vertical_id} />
                                        </div>
                                    )}
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
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={form.data.is_lead_magnet}
                                            onCheckedChange={(v) => form.setData('is_lead_magnet', v === true)}
                                        />
                                        Lead magnet
                                    </label>
                                    <DialogFooter>
                                        <Button type="submit" disabled={form.processing}>
                                            Create
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {pieces.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No content yet. Create a piece to start planning.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Title</th>
                                    <th className="p-3 font-medium">Type</th>
                                    <th className="p-3 font-medium">Funnel</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 text-center font-medium">Lead magnet</th>
                                    <th className="p-3 text-center font-medium">Score</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {pieces.map((piece) => (
                                    <tr key={piece.id} className="hover:bg-muted/40">
                                        <td className="p-3">
                                            <Link href={route('content.pieces.show', piece.id)} className="font-medium hover:underline">
                                                {piece.title}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant="outline">{piece.content_type}</Badge>
                                        </td>
                                        <td className="text-muted-foreground p-3">{piece.funnel_stage ?? '—'}</td>
                                        <td className="p-3">
                                            <Badge variant={statusVariant(piece.status)}>{piece.status}</Badge>
                                        </td>
                                        <td className="p-3 text-center">{piece.is_lead_magnet ? '✓' : '—'}</td>
                                        <td className="p-3 text-center">{piece.optimization_score}/100</td>
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
