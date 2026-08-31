import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ChangeEvent, useState } from 'react';

/**
 * Shared CSV import wizard for companies, leads and deals (IMEX-001) — the
 * same upload → preview → commit flow contacts have.
 */

type Preview = {
    filename: string;
    total: number;
    valid: number;
    invalid: number;
    duplicates: number;
    sample: { label: string; status: string; error: string | null }[];
};
type HistoryRow = { id: number; filename: string | null; imported: number; skipped: number; failed: number; created_at: string };

const statusColor: Record<string, string> = { valid: 'text-green-600', duplicate: 'text-amber-600', invalid: 'text-red-600' };

const COLUMNS_HINT: Record<string, string> = {
    companies: 'Name, Domain, Industry, Phone, Website',
    leads: 'First name, Last name, Email, Phone, Company, Source',
    deals: 'Name, Value, MRR, Contact email, Stage',
};

export default function ImportEntity({ entity, history, preview }: { entity: string; history: HistoryRow[]; preview: Preview | null }) {
    const [file, setFile] = useState<File | null>(null);
    const previewForm = useForm<{ file: File | null }>({ file: null });
    const commitForm = useForm<{ file: File | null }>({ file: null });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: entity.charAt(0).toUpperCase() + entity.slice(1), href: `/crm/${entity}` },
        { title: 'Import', href: `/crm/${entity}/import` },
    ];

    const onSelect = (e: ChangeEvent<HTMLInputElement>) => {
        const selected = e.target.files?.[0] ?? null;
        setFile(selected);
        previewForm.setData('file', selected);
        commitForm.setData('file', selected);
        if (selected) {
            previewForm.transform((d) => ({ ...d, file: selected }));
            previewForm.post(route('crm.entity.import.preview', entity), { preserveScroll: true, preserveState: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Import ${entity}`} />
            <div className="max-w-3xl space-y-6 p-4">
                <Heading title={`Import ${entity}`} description={`Upload a CSV with columns like ${COLUMNS_HINT[entity] ?? 'Name'}.`} />

                <Card>
                    <CardContent className="space-y-4 p-4">
                        <label className="bg-primary text-primary-foreground hover:bg-primary/90 inline-block cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium">
                            Choose CSV file
                            <input type="file" accept=".csv,text/csv" className="hidden" onChange={onSelect} />
                        </label>
                        {file && <span className="text-muted-foreground ml-3 text-sm">{file.name}</span>}
                        <InputError message={previewForm.errors.file} />

                        {preview && (
                            <div className="space-y-3">
                                <div className="flex gap-4 text-sm">
                                    <span>
                                        <strong>{preview.total}</strong> rows
                                    </span>
                                    <span className="text-green-600">{preview.valid} valid</span>
                                    <span className="text-amber-600">{preview.duplicates} duplicates</span>
                                    <span className="text-red-600">{preview.invalid} invalid</span>
                                </div>
                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="p-2 font-medium">Record</th>
                                                <th className="p-2 font-medium">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {preview.sample.map((row, i) => (
                                                <tr key={i}>
                                                    <td className="p-2">{row.label}</td>
                                                    <td className={`p-2 capitalize ${statusColor[row.status] ?? ''}`}>
                                                        {row.status}
                                                        {row.error && ` — ${row.error}`}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <Button
                                    disabled={commitForm.processing || preview.valid === 0}
                                    onClick={() => {
                                        commitForm.transform((d) => ({ ...d, file }));
                                        commitForm.post(route('crm.entity.import.store', entity));
                                    }}
                                >
                                    Import {preview.valid} {entity}
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {history.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Import history</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 p-4 pt-0 text-sm">
                            {history.map((h) => (
                                <div key={h.id} className="flex items-center justify-between">
                                    <span>{h.filename ?? 'import.csv'}</span>
                                    <span className="flex items-center gap-2 text-xs">
                                        <Badge variant="default">{h.imported} imported</Badge>
                                        <Badge variant="secondary">{h.skipped} skipped</Badge>
                                        {h.failed > 0 && <Badge variant="destructive">{h.failed} failed</Badge>}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
