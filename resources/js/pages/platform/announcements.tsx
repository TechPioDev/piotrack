import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Announcements', href: '/platform/announcements' }];

type Announcement = {
    id: number;
    title: string;
    body: string;
    audience: string;
    type: string;
    published_at: string | null;
};

const types = ['announcement', 'release_note'];
const audiences = ['all', 'admins', 'clients'];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function formatTime(iso: string | null): string {
    return iso === null ? '—' : new Date(iso).toLocaleString();
}

function NewAnnouncementDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ title: string; body: string; audience: string; type: string; publish: boolean }>({
        title: '',
        body: '',
        audience: audiences[0],
        type: types[0],
        publish: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('platform.announcements.store'), {
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
                <Button>New announcement</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New announcement</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="announcement_title">Title</Label>
                        <Input id="announcement_title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="announcement_body">Body</Label>
                        <textarea
                            id="announcement_body"
                            className={textareaClass}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="announcement_type">Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger id="announcement_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type.replace(/_/g, ' ')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="announcement_audience">Audience</Label>
                            <Select value={form.data.audience} onValueChange={(v) => form.setData('audience', v)}>
                                <SelectTrigger id="announcement_audience">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {audiences.map((audience) => (
                                        <SelectItem key={audience} value={audience}>
                                            {audience}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.audience} />
                        </div>
                    </div>
                    <label className="flex items-start gap-3 rounded-lg border p-3" htmlFor="announcement_publish">
                        <Checkbox
                            id="announcement_publish"
                            checked={form.data.publish}
                            onCheckedChange={(v) => form.setData('publish', v === true)}
                        />
                        <span className="text-sm">
                            <span className="font-medium">Publish now</span>
                            <span className="text-muted-foreground block">
                                Published announcements are visible to the chosen audience immediately. Leave this off to save a draft.
                            </span>
                        </span>
                    </label>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PlatformAnnouncements({ announcements }: { announcements: Announcement[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Announcements" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Announcements" description="Product announcements and release notes pushed to tenants" />
                    <NewAnnouncementDialog />
                </div>

                {announcements.length === 0 ? (
                    <p className="text-muted-foreground text-sm">Nothing has been announced yet.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3">Title</th>
                                    <th className="p-3">Type</th>
                                    <th className="p-3">Audience</th>
                                    <th className="p-3">Published</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {announcements.map((announcement) => (
                                    <tr key={announcement.id} className="hover:bg-muted/40 align-top">
                                        <td className="max-w-96 p-3">
                                            <p className="font-medium">{announcement.title}</p>
                                            <p className="text-muted-foreground whitespace-pre-line">{announcement.body}</p>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant="outline">{announcement.type.replace(/_/g, ' ')}</Badge>
                                        </td>
                                        <td className="text-muted-foreground p-3">{announcement.audience}</td>
                                        <td className="p-3">
                                            {announcement.published_at === null ? (
                                                <Badge variant="secondary">Draft</Badge>
                                            ) : (
                                                <span className="text-muted-foreground">{formatTime(announcement.published_at)}</span>
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
