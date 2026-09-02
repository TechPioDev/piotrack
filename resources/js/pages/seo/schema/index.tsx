import { ConfirmAction } from '@/components/confirm-action';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Schema', href: '/seo/schema' }];

type SchemaItem = {
    id: number;
    schema_type: string;
    url: string | null;
    jsonld: string;
};

type SchemaForm = {
    schema_type: string;
    url: string;
    name: string;
    phone: string;
    website: string;
    street: string;
    city: string;
    region: string;
    postal_code: string;
    country: string;
    service_type: string;
    headline: string;
    author: string;
    job_title: string;
};

const DATA_FIELDS: { key: Exclude<keyof SchemaForm, 'schema_type' | 'url'>; label: string }[] = [
    { key: 'name', label: 'Name' },
    { key: 'phone', label: 'Phone' },
    { key: 'website', label: 'Website' },
    { key: 'street', label: 'Street' },
    { key: 'city', label: 'City' },
    { key: 'region', label: 'Region' },
    { key: 'postal_code', label: 'Postal code' },
    { key: 'country', label: 'Country' },
    { key: 'service_type', label: 'Service type' },
    { key: 'headline', label: 'Headline' },
    { key: 'author', label: 'Author' },
    { key: 'job_title', label: 'Job title' },
];

export default function Schema({ types, items }: { types: string[]; items: SchemaItem[] }) {
    const { can } = usePermissions();
    const canManage = can('seo.audits.manage');
    const form = useForm<SchemaForm>({
        schema_type: types[0] ?? '',
        url: '',
        name: '',
        phone: '',
        website: '',
        street: '',
        city: '',
        region: '',
        postal_code: '',
        country: '',
        service_type: '',
        headline: '',
        author: '',
        job_title: '',
    });

    const generate: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => {
            const nested: Record<string, string> = {};
            for (const field of DATA_FIELDS) {
                const value = data[field.key];
                if (value.trim() !== '') {
                    nested[field.key] = value;
                }
            }
            return { schema_type: data.schema_type, url: data.url, data: nested };
        });
        form.post(route('seo.schema.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Schema" />
            <div className="space-y-6 p-4">
                <Heading title="Schema" description="Generate structured data (JSON-LD) for your pages" />

                {canManage && (
                    <Card>
                        <CardContent className="p-4">
                            <form onSubmit={generate} className="space-y-3">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1">
                                        <Label htmlFor="schema_type">Schema type</Label>
                                        <Select value={form.data.schema_type} onValueChange={(v) => form.setData('schema_type', v)}>
                                            <SelectTrigger id="schema_type">
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
                                        <InputError message={form.errors.schema_type} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="url">Page URL (optional)</Label>
                                        <Input
                                            id="url"
                                            type="url"
                                            placeholder="https://example.com/page"
                                            value={form.data.url}
                                            onChange={(e) => form.setData('url', e.target.value)}
                                        />
                                        <InputError message={form.errors.url} />
                                    </div>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {DATA_FIELDS.map((field) => (
                                        <div key={field.key} className="grid gap-1">
                                            <Label htmlFor={field.key}>{field.label}</Label>
                                            <Input
                                                id={field.key}
                                                value={form.data[field.key]}
                                                onChange={(e) => form.setData(field.key, e.target.value)}
                                            />
                                        </div>
                                    ))}
                                </div>

                                <p className="text-muted-foreground text-xs">Fill only the fields relevant to this type. Empty fields are ignored.</p>

                                <Button type="submit" disabled={form.processing}>
                                    Generate schema
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div>
                    <h3 className="mb-2 text-sm font-medium">Saved schema</h3>
                    {items.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No schema yet. Generate structured data to embed it on your pages.</p>
                    ) : (
                        <div className="space-y-3">
                            {items.map((item) => (
                                <Card key={item.id}>
                                    <CardContent className="space-y-2 p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="outline">{item.schema_type}</Badge>
                                                <span className="text-muted-foreground text-sm break-all">{item.url ?? '—'}</span>
                                            </div>
                                            {canManage && (
                                                <ConfirmAction
                                                    title={`Delete this ${item.schema_type} schema?`}
                                                    description="Pages embedding this JSON-LD block lose it. This cannot be undone."
                                                    confirmLabel="Delete"
                                                    onConfirm={() => router.delete(route('seo.schema.destroy', item.id), { preserveScroll: true })}
                                                >
                                                    <Button size="sm" variant="ghost" className="text-destructive">
                                                        Delete
                                                    </Button>
                                                </ConfirmAction>
                                            )}
                                        </div>
                                        <pre className="bg-muted/50 overflow-x-auto rounded-md border p-3 text-xs">{item.jsonld}</pre>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
