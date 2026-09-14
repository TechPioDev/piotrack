import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Landing Pages', href: '/marketing/landing-pages' }];

type LandingPage = {
    id: number;
    name: string;
    slug: string;
    headline: string | null;
    status: string;
    view_count: number;
    public_url: string;
};

type FormOption = { id: number; name: string };

export default function LandingPages({ pages, forms }: { pages: LandingPage[]; forms: FormOption[] }) {
    const { can } = usePermissions();
    const canManage = can('marketing.forms.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{
        name: string;
        headline: string;
        subheadline: string;
        body_html: string;
        form_id: string;
    }>({
        name: '',
        headline: '',
        subheadline: '',
        body_html: '',
        form_id: '',
    });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('marketing.landing-pages.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Landing Pages" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Landing Pages" description={`${pages.length} total`} />
                    {canManage && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>New page</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>New landing page</DialogTitle>
                                <form onSubmit={create} className="space-y-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="name">Name</Label>
                                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                        <InputError message={form.errors.name} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="headline">Headline</Label>
                                        <Input id="headline" value={form.data.headline} onChange={(e) => form.setData('headline', e.target.value)} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="subheadline">Subheadline</Label>
                                        <Input
                                            id="subheadline"
                                            value={form.data.subheadline}
                                            onChange={(e) => form.setData('subheadline', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="body_html">Body HTML</Label>
                                        <textarea
                                            id="body_html"
                                            className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                            value={form.data.body_html}
                                            onChange={(e) => form.setData('body_html', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="form_id">Form</Label>
                                        <Select value={form.data.form_id} onValueChange={(v) => form.setData('form_id', v)}>
                                            <SelectTrigger id="form_id">
                                                <SelectValue placeholder="None" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {forms.map((formOption) => (
                                                    <SelectItem key={formOption.id} value={String(formOption.id)}>
                                                        {formOption.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
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

                {pages.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No landing pages yet. Create one to start driving conversions.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3 text-right">Views</th>
                                    <th className="p-3">Public URL</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {pages.map((page) => (
                                    <tr key={page.id} className="hover:bg-muted/40">
                                        <td className="p-3">
                                            <span className="font-medium">{page.name}</span>
                                            {page.headline && <span className="text-muted-foreground"> · {page.headline}</span>}
                                        </td>
                                        <td className="p-3">
                                            <Badge variant={page.status === 'published' ? 'default' : 'secondary'}>{page.status}</Badge>
                                        </td>
                                        <td className="p-3 text-right">{page.view_count}</td>
                                        <td className="p-3">
                                            <a href={page.public_url} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                                                {page.public_url}
                                            </a>
                                        </td>
                                        <td className="p-3 text-right">
                                            {canManage && (
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(
                                                                route('marketing.landing-pages.publish', page.id),
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        {page.status === 'published' ? 'Unpublish' : 'Publish'}
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() => router.delete(route('marketing.landing-pages.destroy', page.id))}
                                                    >
                                                        Delete
                                                    </Button>
                                                </div>
                                            )}
                                        </td>
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
