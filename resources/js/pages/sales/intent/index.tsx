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
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Intent', href: '/sales/intent' }];

type Signal = {
    id: number;
    contact: string | null;
    type: string;
    weight: number;
    url: string | null;
    occurred_at: string | null;
};

type IntentContact = {
    id: number;
    name: string;
    intent_score: number;
    high_intent: boolean;
    buying_window: boolean;
    next_action: string | null;
};

type ContactOption = {
    id: number;
    name: string;
};

function formatTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

function RecordSignalDialog({ types, contactOptions }: { types: string[]; contactOptions: ContactOption[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ contact_id: string; type: string; weight: string; url: string }>({
        contact_id: '',
        type: types[0] ?? '',
        weight: '',
        url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, contact_id: Number(data.contact_id), weight: Number(data.weight) }));
        form.post(route('sales.intent.store'), {
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
                <Button>Record signal</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Record intent signal</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="signal_contact">Contact</Label>
                        <Select value={form.data.contact_id} onValueChange={(v) => form.setData('contact_id', v)}>
                            <SelectTrigger id="signal_contact">
                                <SelectValue placeholder="Select a contact" />
                            </SelectTrigger>
                            <SelectContent>
                                {contactOptions.map((contact) => (
                                    <SelectItem key={contact.id} value={String(contact.id)}>
                                        {contact.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.contact_id} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="signal_type">Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger id="signal_type">
                                    <SelectValue placeholder="Select a type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="signal_weight">Weight</Label>
                            <Input
                                id="signal_weight"
                                type="number"
                                value={form.data.weight}
                                onChange={(e) => form.setData('weight', e.target.value)}
                            />
                            <InputError message={form.errors.weight} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="signal_url">URL</Label>
                        <Input id="signal_url" type="url" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} />
                        <InputError message={form.errors.url} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Record
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Intent({
    signals,
    contacts,
    types,
    contactOptions,
}: {
    signals: Signal[];
    contacts: IntentContact[];
    types: string[];
    contactOptions: ContactOption[];
}) {
    const { can } = usePermissions();
    const canManage = can('sales.scoring.manage');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Intent" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Buyer intent" description="High-intent contacts and the signals behind them" />
                    {canManage && <RecordSignalDialog types={types} contactOptions={contactOptions} />}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">High-intent contacts</h3>
                    {contacts.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No intent yet. Record a signal to start ranking contacts.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Name</th>
                                        <th className="p-3 text-center font-medium">Intent score</th>
                                        <th className="p-3 font-medium">Signal</th>
                                        <th className="p-3 font-medium">Next action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {contacts.map((contact) => (
                                        <tr key={contact.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{contact.name}</td>
                                            <td className="p-3 text-center">{contact.intent_score}</td>
                                            <td className="p-3">
                                                <div className="flex gap-1">
                                                    {contact.high_intent && <Badge>High intent</Badge>}
                                                    {contact.buying_window && <Badge variant="secondary">Buying window</Badge>}
                                                </div>
                                            </td>
                                            <td className="text-muted-foreground p-3">{contact.next_action ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Recent signals</h3>
                    {signals.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No signals yet. Record a signal to track buyer activity.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Contact</th>
                                        <th className="p-3 font-medium">Type</th>
                                        <th className="p-3 text-center font-medium">Weight</th>
                                        <th className="p-3 font-medium">Occurred</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {signals.map((signal) => (
                                        <tr key={signal.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{signal.contact ?? '—'}</td>
                                            <td className="p-3">
                                                <Badge variant="outline">{signal.type}</Badge>
                                            </td>
                                            <td className="p-3 text-center">{signal.weight}</td>
                                            <td className="text-muted-foreground p-3">{formatTime(signal.occurred_at)}</td>
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
