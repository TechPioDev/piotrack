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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Booking', href: '/sales/booking' }];

type BookingPage = {
    id: number;
    name: string;
    slug: string;
    meeting_type: string;
    duration_minutes: number;
    assignment: string;
    is_active: boolean;
    owner: string | null;
    public_url: string;
    feed_url: string | null;
};

type Booking = {
    id: number;
    name: string;
    email: string;
    page: string | null;
    scheduled_at: string;
    status: string;
};

type Member = {
    id: number;
    name: string;
};

type Assignment = 'fixed' | 'round_robin' | 'territory';
type BookingStatus = 'booked' | 'completed' | 'canceled' | 'no_show';

const ASSIGNMENTS: Assignment[] = ['fixed', 'round_robin', 'territory'];
const BOOKING_STATUSES: BookingStatus[] = ['booked', 'completed', 'canceled', 'no_show'];

function formatTime(iso: string): string {
    return new Date(iso).toLocaleString();
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' {
    if (status === 'no_show') return 'destructive';
    if (status === 'canceled') return 'secondary';
    return 'default';
}

function NewPageDialog({ members }: { members: Member[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; meeting_type: string; duration_minutes: string; assignment: Assignment; user_id: string }>({
        name: '',
        meeting_type: '',
        duration_minutes: '',
        assignment: 'fixed',
        user_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            duration_minutes: Number(data.duration_minutes),
            user_id: data.user_id ? Number(data.user_id) : null,
        }));
        form.post(route('sales.booking.store'), {
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
                <Button>New booking page</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New booking page</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="page_name">Name</Label>
                        <Input id="page_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="page_meeting_type">Meeting type</Label>
                            <Input
                                id="page_meeting_type"
                                value={form.data.meeting_type}
                                onChange={(e) => form.setData('meeting_type', e.target.value)}
                            />
                            <InputError message={form.errors.meeting_type} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="page_duration">Duration (minutes)</Label>
                            <Input
                                id="page_duration"
                                type="number"
                                value={form.data.duration_minutes}
                                onChange={(e) => form.setData('duration_minutes', e.target.value)}
                            />
                            <InputError message={form.errors.duration_minutes} />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="page_assignment">Assignment</Label>
                            <Select value={form.data.assignment} onValueChange={(v) => form.setData('assignment', v as Assignment)}>
                                <SelectTrigger id="page_assignment">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ASSIGNMENTS.map((assignment) => (
                                        <SelectItem key={assignment} value={assignment}>
                                            {assignment}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.assignment} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="page_owner">Owner</Label>
                            <Select value={form.data.user_id} onValueChange={(v) => form.setData('user_id', v)}>
                                <SelectTrigger id="page_owner">
                                    <SelectValue placeholder="Unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    {members.map((member) => (
                                        <SelectItem key={member.id} value={String(member.id)}>
                                            {member.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.user_id} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Booking({ pages, bookings, members }: { pages: BookingPage[]; bookings: Booking[]; members: Member[] }) {
    const { can } = usePermissions();
    const canManage = can('sales.booking.manage');

    const togglePublish = (id: number) => router.post(route('sales.booking.publish', id), {}, { preserveScroll: true });
    const removePage = (id: number) => router.delete(route('sales.booking.destroy', id), { preserveScroll: true });
    const setStatus = (id: number, status: string) => router.post(route('sales.booking.status', id), { status }, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Booking" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Booking" description="Booking pages and scheduled meetings" />
                    {canManage && <NewPageDialog members={members} />}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Booking pages</h3>
                    {pages.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No booking pages yet. Create a page to let prospects book time.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Name</th>
                                        <th className="p-3 font-medium">Meeting type</th>
                                        <th className="p-3 text-center font-medium">Duration</th>
                                        <th className="p-3 font-medium">Assignment</th>
                                        <th className="p-3 font-medium">Active</th>
                                        <th className="p-3 font-medium">Owner</th>
                                        <th className="p-3 font-medium">Link</th>
                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {pages.map((page) => (
                                        <tr key={page.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{page.name}</td>
                                            <td className="text-muted-foreground p-3">{page.meeting_type}</td>
                                            <td className="p-3 text-center">{page.duration_minutes} min</td>
                                            <td className="p-3">
                                                <Badge variant="outline">{page.assignment}</Badge>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant={page.is_active ? 'default' : 'secondary'}>
                                                    {page.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="text-muted-foreground p-3">{page.owner ?? '—'}</td>
                                            <td className="p-3">
                                                <a href={page.public_url} target="_blank" rel="noreferrer" className="break-all hover:underline">
                                                    {page.public_url}
                                                </a>
                                                {page.feed_url && (
                                                    <p className="text-muted-foreground mt-1 text-xs break-all">
                                                        Calendar feed (subscribe in Google/Outlook/Apple): {page.feed_url}
                                                    </p>
                                                )}
                                            </td>
                                            {canManage && (
                                                <td className="p-3">
                                                    <div className="flex justify-end gap-2">
                                                        <Button size="sm" variant="secondary" onClick={() => togglePublish(page.id)}>
                                                            {page.is_active ? 'Unpublish' : 'Publish'}
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removePage(page.id)}
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
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Bookings</h3>
                    {bookings.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No bookings yet. Bookings appear here once prospects schedule time.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Name</th>
                                        <th className="p-3 font-medium">Email</th>
                                        <th className="p-3 font-medium">Page</th>
                                        <th className="p-3 font-medium">Scheduled</th>
                                        <th className="p-3 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {bookings.map((booking) => (
                                        <tr key={booking.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{booking.name}</td>
                                            <td className="text-muted-foreground p-3">{booking.email}</td>
                                            <td className="text-muted-foreground p-3">{booking.page ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{formatTime(booking.scheduled_at)}</td>
                                            <td className="p-3">
                                                {canManage ? (
                                                    <Select value={booking.status} onValueChange={(v) => setStatus(booking.id, v)}>
                                                        <SelectTrigger className="w-36">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {BOOKING_STATUSES.map((status) => (
                                                                <SelectItem key={status} value={status}>
                                                                    {status}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                ) : (
                                                    <Badge variant={statusVariant(booking.status)}>{booking.status}</Badge>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
