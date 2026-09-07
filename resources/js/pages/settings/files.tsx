import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ChangeEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Files', href: '/settings/files' }];

type FileRow = {
    id: number;
    name: string;
    mime: string | null;
    size: number;
    uploaded_by: string | null;
    attached_to: string | null;
    client_visible: boolean;
    created_at: string;
};

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function Files({ files }: { files: FileRow[] }) {
    const { can } = usePermissions();
    const form = useForm<{ file: File | null }>({ file: null });

    const onSelect = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        form.setData('file', file);
        if (file) {
            form.post(route('files.store'), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Files" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <HeadingSmall title="Files" description="Documents and brand assets for this organization" />
                        {can('files.manage') && (
                            <label className="bg-primary text-primary-foreground hover:bg-primary/90 cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium">
                                Upload file
                                <input type="file" className="hidden" onChange={onSelect} disabled={form.processing} />
                            </label>
                        )}
                    </div>
                    <InputError message={form.errors.file} />

                    {files.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No files yet. Upload documents or brand assets to keep them handy.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Name</th>
                                        <th className="p-3 font-medium">Size</th>
                                        <th className="p-3 font-medium">Uploaded by</th>
                                        <th className="p-3 font-medium">Attached to</th>
                                        <th className="p-3 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {files.map((file) => (
                                        <tr key={file.id}>
                                            <td className="p-3 font-medium">{file.name}</td>
                                            <td className="text-muted-foreground p-3">{formatSize(file.size)}</td>
                                            <td className="text-muted-foreground p-3">{file.uploaded_by ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{file.attached_to ?? '—'}</td>
                                            <td className="p-3 text-right">
                                                <Button asChild variant="ghost" size="sm">
                                                    <a href={route('files.download', file.id)}>Download</a>
                                                </Button>
                                                {can('files.manage') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => router.patch(route('files.visibility', file.id), {}, { preserveScroll: true })}
                                                    >
                                                        {file.client_visible ? 'Portal ✓' : 'Share to portal'}
                                                    </Button>
                                                )}
                                                {can('files.manage') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600"
                                                        onClick={() => router.delete(route('files.destroy', file.id), { preserveScroll: true })}
                                                    >
                                                        Delete
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
