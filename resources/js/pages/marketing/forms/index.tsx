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
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Forms', href: '/marketing/forms' }];

type MarketingForm = {
    id: number;
    name: string;
    slug: string;
    status: string;
    submission_count: number;
    public_url: string;
};

type ListOption = { id: number; name: string };

type FieldType = 'text' | 'email' | 'tel' | 'textarea';
type FieldRow = { name: string; label: string; type: FieldType; required: boolean };

export default function Forms({ forms, lists }: { forms: MarketingForm[]; lists: ListOption[] }) {
    const { can } = usePermissions();
    const canManage = can('marketing.forms.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{
        name: string;
        target_list_id: string;
        lifecycle_stage: string;
        fields: FieldRow[];
    }>({
        name: '',
        target_list_id: '',
        lifecycle_stage: 'lead',
        fields: [{ name: '', label: '', type: 'text', required: false }],
    });

    const updateField = (index: number, patch: Partial<FieldRow>) => {
        form.setData(
            'fields',
            form.data.fields.map((field, i) => (i === index ? { ...field, ...patch } : field)),
        );
    };

    const addField = () => form.setData('fields', [...form.data.fields, { name: '', label: '', type: 'text', required: false }]);

    const removeField = (index: number) =>
        form.setData(
            'fields',
            form.data.fields.filter((_, i) => i !== index),
        );

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('marketing.forms.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Forms" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Forms" description={`${forms.length} total`} />
                    {canManage && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>New form</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>New form</DialogTitle>
                                <form onSubmit={create} className="space-y-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="name">Name</Label>
                                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                        <InputError message={form.errors.name} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="target_list_id">Target list</Label>
                                            <Select value={form.data.target_list_id} onValueChange={(v) => form.setData('target_list_id', v)}>
                                                <SelectTrigger id="target_list_id">
                                                    <SelectValue placeholder="None" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {lists.map((list) => (
                                                        <SelectItem key={list.id} value={String(list.id)}>
                                                            {list.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="lifecycle_stage">Lifecycle stage</Label>
                                            <Input
                                                id="lifecycle_stage"
                                                value={form.data.lifecycle_stage}
                                                onChange={(e) => form.setData('lifecycle_stage', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Label>Fields</Label>
                                            <Button type="button" size="sm" variant="outline" onClick={addField}>
                                                Add field
                                            </Button>
                                        </div>
                                        {form.data.fields.map((field, index) => (
                                            <div key={index} className="space-y-2 rounded-md border p-3">
                                                <div className="grid grid-cols-2 gap-2">
                                                    <Input
                                                        placeholder="name"
                                                        value={field.name}
                                                        onChange={(e) => updateField(index, { name: e.target.value })}
                                                    />
                                                    <Input
                                                        placeholder="label"
                                                        value={field.label}
                                                        onChange={(e) => updateField(index, { label: e.target.value })}
                                                    />
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <Select value={field.type} onValueChange={(v) => updateField(index, { type: v as FieldType })}>
                                                        <SelectTrigger className="h-9 flex-1">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="text">Text</SelectItem>
                                                            <SelectItem value="email">Email</SelectItem>
                                                            <SelectItem value="tel">Phone</SelectItem>
                                                            <SelectItem value="textarea">Textarea</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    <label className="flex items-center gap-2 text-sm">
                                                        <Checkbox
                                                            checked={field.required}
                                                            onCheckedChange={(v) => updateField(index, { required: v === true })}
                                                        />
                                                        Required
                                                    </label>
                                                    {form.data.fields.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removeField(index)}
                                                        >
                                                            Remove
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
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

                {forms.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No forms yet. Create a form to start capturing leads.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3 text-right">Submissions</th>
                                    <th className="p-3">Public URL</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {forms.map((formRow) => (
                                    <tr key={formRow.id} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{formRow.name}</td>
                                        <td className="p-3">
                                            <Badge variant={formRow.status === 'published' ? 'default' : 'secondary'}>{formRow.status}</Badge>
                                        </td>
                                        <td className="p-3 text-right">{formRow.submission_count}</td>
                                        <td className="p-3">
                                            <a href={formRow.public_url} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                                                {formRow.public_url}
                                            </a>
                                        </td>
                                        <td className="p-3 text-right">
                                            {canManage && (
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(route('marketing.forms.publish', formRow.id), {}, { preserveScroll: true })
                                                        }
                                                    >
                                                        {formRow.status === 'published' ? 'Unpublish' : 'Publish'}
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() => router.delete(route('marketing.forms.destroy', formRow.id))}
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
