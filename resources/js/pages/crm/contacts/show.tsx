import { ActivityTimeline, type Activity } from '@/components/crm/activity-timeline';
import Heading from '@/components/heading';
import { InitialAvatar } from '@/components/initial-avatar';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Contact = {
    id: number;
    first_name: string;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    title: string | null;
    lead_source: string | null;
    campaign: string | null;
    company: { id: number; name: string } | null;
    owner: string | null;
};

type DealRow = { id: number; name: string; value: number; status: string };

function VideoMessageDialog({ contactId }: { contactId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ video_url: string; subject: string; message: string }>({
        video_url: '',
        subject: 'A quick video for you',
        message: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('crm.contacts.video-message', contactId), {
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
                    Send video
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Personalized video message</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    Record on any host (Loom, Vidyard, an unlisted YouTube link), paste the link, add a personal note. The email is merge-tag
                    personalized and the watch button is click-tracked.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="vm_url">Video URL (https)</Label>
                        <Input id="vm_url" type="url" value={form.data.video_url} onChange={(e) => form.setData('video_url', e.target.value)} />
                        <InputError message={form.errors.video_url} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="vm_subject">Subject</Label>
                        <Input id="vm_subject" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                        <InputError message={form.errors.subject} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="vm_message">Message</Label>
                        <textarea
                            id="vm_message"
                            className="border-input bg-background flex min-h-24 w-full rounded-md border px-3 py-2 text-sm"
                            value={form.data.message}
                            onChange={(e) => form.setData('message', e.target.value)}
                        />
                        <InputError message={form.errors.message} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Send
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ContactShow({ contact, activities, deals }: { contact: Contact; activities: Activity[]; deals: DealRow[] }) {
    const { can } = usePermissions();
    const name = `${contact.first_name} ${contact.last_name ?? ''}`.trim();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Contacts', href: '/crm/contacts' },
        { title: name, href: `/crm/contacts/${contact.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={name} />
            <div className="grid gap-4 p-4 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-1">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <InitialAvatar name={name} className="size-10 text-sm" />
                            <Heading title={name} description={contact.title ?? undefined} />
                        </div>
                        {can('crm.contact.update') && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.post(route('crm.contacts.enrich', contact.id), {}, { preserveScroll: true })}
                            >
                                Enrich
                            </Button>
                        )}
                        {can('crm.contact.update') && <VideoMessageDialog contactId={contact.id} />}
                        {can('crm.contact.delete') && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-red-600"
                                onClick={() => router.delete(route('crm.contacts.destroy', contact.id))}
                            >
                                Delete
                            </Button>
                        )}
                    </div>
                    <Card>
                        <CardContent className="space-y-2 p-4 text-sm">
                            <Row label="Email" value={contact.email} />
                            <Row label="Phone" value={contact.phone} />
                            <Row
                                label="Company"
                                value={
                                    contact.company ? (
                                        <Link
                                            href={route('crm.companies.show', contact.company.id)}
                                            className="hover:text-brand-strong hover:underline"
                                        >
                                            {contact.company.name}
                                        </Link>
                                    ) : null
                                }
                            />
                            <Row label="Lead source" value={contact.lead_source} />
                            <Row label="Campaign" value={contact.campaign} />
                            <Row label="Owner" value={contact.owner} />
                        </CardContent>
                    </Card>

                    {deals.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Deals</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1 p-4 pt-0 text-sm">
                                {deals.map((d) => (
                                    <div key={d.id} className="flex items-center justify-between">
                                        <Link href={route('crm.deals.show', d.id)} className="hover:underline">
                                            {d.name}
                                        </Link>
                                        <span className="flex items-center gap-2">
                                            <Badge variant="outline">{d.status}</Badge>
                                            {formatMoney(d.value)}
                                        </span>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Activity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ActivityTimeline
                                subjectType="contact"
                                subjectId={contact.id}
                                activities={activities}
                                canManage={can('crm.activity.manage')}
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right">{value ?? '—'}</span>
        </div>
    );
}
