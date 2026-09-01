import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Keywords', href: '/seo/keywords' }];

type Keyword = {
    id: number;
    phrase: string;
    intent: string;
    type: string | null;
    search_volume: number | null;
    difficulty: number | null;
    mapped_url: string | null;
    cluster: string | null;
    location: string | null;
    current_position: number | null;
    page_one: boolean;
    top_three: boolean;
};

type Gap = { id: number; phrase: string };

const INTENTS = ['informational', 'commercial', 'transactional', 'navigational'] as const;

function RankDialog({ keyword }: { keyword: Keyword }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ domain: string; location: string }>({ domain: '', location: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('seo.keywords.rank', keyword.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Check rank
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Check rank</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`domain-${keyword.id}`}>Domain</Label>
                        <Input
                            id={`domain-${keyword.id}`}
                            placeholder="example.com"
                            value={form.data.domain}
                            onChange={(e) => form.setData('domain', e.target.value)}
                        />
                        <InputError message={form.errors.domain} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`location-${keyword.id}`}>Location (optional)</Label>
                        <Input id={`location-${keyword.id}`} value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} />
                        <InputError message={form.errors.location} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Check rank
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Keywords({ keywords, gap }: { keywords: Keyword[]; gap: Gap[] }) {
    const { can } = usePermissions();
    const canManage = can('seo.keywords.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{
        phrase: string;
        intent: (typeof INTENTS)[number];
        type: string;
        location: string;
        search_volume: string;
        difficulty: string;
        mapped_url: string;
    }>({
        phrase: '',
        intent: 'informational',
        type: '',
        location: '',
        search_volume: '',
        difficulty: '',
        mapped_url: '',
    });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('seo.keywords.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Keywords" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Keywords" description={`${keywords.length} tracked`} />
                    {canManage && (
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={() => router.post(route('seo.keywords.recluster'), {}, { preserveScroll: true })}>
                                Recluster
                            </Button>
                            <Dialog open={open} onOpenChange={setOpen}>
                                <DialogTrigger asChild>
                                    <Button>Add keyword</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>Add keyword</DialogTitle>
                                    <form onSubmit={create} className="space-y-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="phrase">Phrase</Label>
                                            <Input id="phrase" value={form.data.phrase} onChange={(e) => form.setData('phrase', e.target.value)} />
                                            <InputError message={form.errors.phrase} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-1">
                                                <Label htmlFor="intent">Intent</Label>
                                                <Select
                                                    value={form.data.intent}
                                                    onValueChange={(v) => form.setData('intent', v as (typeof INTENTS)[number])}
                                                >
                                                    <SelectTrigger id="intent">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {INTENTS.map((intent) => (
                                                            <SelectItem key={intent} value={intent} className="capitalize">
                                                                {intent}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError message={form.errors.intent} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="type">Type</Label>
                                                <Input id="type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} />
                                                <InputError message={form.errors.type} />
                                            </div>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="kw_location">Location (for local keywords)</Label>
                                            <Input
                                                id="kw_location"
                                                placeholder="e.g. Philadelphia, PA — or a state, service area or neighborhood"
                                                value={form.data.location}
                                                onChange={(e) => form.setData('location', e.target.value)}
                                            />
                                            <InputError message={form.errors.location} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-1">
                                                <Label htmlFor="search_volume">Search volume</Label>
                                                <Input
                                                    id="search_volume"
                                                    type="number"
                                                    value={form.data.search_volume}
                                                    onChange={(e) => form.setData('search_volume', e.target.value)}
                                                />
                                                <InputError message={form.errors.search_volume} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="difficulty">Difficulty</Label>
                                                <Input
                                                    id="difficulty"
                                                    type="number"
                                                    value={form.data.difficulty}
                                                    onChange={(e) => form.setData('difficulty', e.target.value)}
                                                />
                                                <InputError message={form.errors.difficulty} />
                                            </div>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="mapped_url">Mapped URL</Label>
                                            <Input
                                                id="mapped_url"
                                                type="url"
                                                placeholder="https://example.com/page"
                                                value={form.data.mapped_url}
                                                onChange={(e) => form.setData('mapped_url', e.target.value)}
                                            />
                                            <InputError message={form.errors.mapped_url} />
                                        </div>
                                        <DialogFooter>
                                            <Button type="submit" disabled={form.processing}>
                                                Add keyword
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    )}
                </div>

                {keywords.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No keywords yet. Add a keyword to start tracking rankings.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Phrase</th>
                                    <th className="p-3 font-medium">Intent</th>
                                    <th className="p-3 font-medium">Cluster</th>
                                    <th className="p-3 font-medium">Location</th>
                                    <th className="p-3 font-medium">Position</th>
                                    <th className="p-3 font-medium">Mapped URL</th>
                                    {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {keywords.map((keyword) => (
                                    <tr key={keyword.id} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{keyword.phrase}</td>
                                        <td className="p-3">
                                            <Badge variant="outline">{keyword.intent}</Badge>
                                        </td>
                                        <td className="text-muted-foreground p-3">{keyword.cluster ?? '—'}</td>
                                        <td className="text-muted-foreground p-3">{keyword.location ?? '—'}</td>
                                        <td className="p-3">
                                            <div className="flex items-center gap-2">
                                                <span>{keyword.current_position ?? '—'}</span>
                                                {keyword.top_three ? (
                                                    <Badge>Top 3</Badge>
                                                ) : keyword.page_one ? (
                                                    <Badge variant="secondary">Page 1</Badge>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="text-muted-foreground p-3 break-all">{keyword.mapped_url ?? '—'}</td>
                                        {canManage && (
                                            <td className="p-3">
                                                <div className="flex justify-end gap-2">
                                                    <RankDialog keyword={keyword} />
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive"
                                                        onClick={() =>
                                                            router.delete(route('seo.keywords.destroy', keyword.id), { preserveScroll: true })
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

                <Card>
                    <CardContent className="space-y-2 p-4">
                        <h3 className="text-sm font-medium">Content gap</h3>
                        {gap.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No unmapped keywords. Map keywords to pages to close gaps.</p>
                        ) : (
                            <ul className="flex flex-wrap gap-2">
                                {gap.map((item) => (
                                    <li key={item.id}>
                                        <Badge variant="secondary">{item.phrase}</Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
