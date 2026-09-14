import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Prompts', href: '/ai/prompts' }];

type Template = {
    id: number;
    key: string;
    version: number;
    description: string | null;
    system: string | null;
    template: string;
    is_active: boolean;
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 font-mono text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

type PublishSeed = {
    key: string;
    system: string;
    template: string;
    description: string;
};

function groupByKey(templates: Template[]): { key: string; versions: Template[] }[] {
    const groups = new Map<string, Template[]>();

    for (const template of templates) {
        const bucket = groups.get(template.key);
        if (bucket === undefined) {
            groups.set(template.key, [template]);
        } else {
            bucket.push(template);
        }
    }

    return [...groups.entries()]
        .map(([key, versions]) => ({ key, versions: [...versions].sort((a, b) => b.version - a.version) }))
        .sort((a, b) => a.key.localeCompare(b.key));
}

function PublishDialog({
    knownKeys,
    seed,
    label,
    variant,
    size,
}: {
    knownKeys: string[];
    seed?: PublishSeed;
    label: string;
    variant?: 'secondary';
    size?: 'sm';
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<PublishSeed>({
        key: seed?.key ?? '',
        system: seed?.system ?? '',
        template: seed?.template ?? '',
        description: seed?.description ?? '',
    });

    const fieldId = (name: string) => `publish_${name}_${seed?.key ?? 'new'}`;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('ai.prompts.publish'), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size={size} variant={variant}>
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogTitle>Publish prompt version</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    Publishing never overwrites anything: it creates a <span className="font-medium">new version</span> under this key and makes it
                    active. Earlier versions stay on record and can be reactivated.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={fieldId('key')}>Key</Label>
                        <Input
                            id={fieldId('key')}
                            list={fieldId('keys')}
                            value={form.data.key}
                            onChange={(e) => form.setData('key', e.target.value)}
                            placeholder="sales.qualify"
                        />
                        <datalist id={fieldId('keys')}>
                            {knownKeys.map((key) => (
                                <option key={key} value={key} />
                            ))}
                        </datalist>
                        <InputError message={form.errors.key} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={fieldId('description')}>Description</Label>
                        <Input
                            id={fieldId('description')}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="What changed in this version"
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={fieldId('system')}>System prompt</Label>
                        <textarea
                            id={fieldId('system')}
                            className={textareaClass}
                            value={form.data.system}
                            onChange={(e) => form.setData('system', e.target.value)}
                        />
                        <InputError message={form.errors.system} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={fieldId('template')}>Template</Label>
                        <textarea
                            id={fieldId('template')}
                            className={textareaClass}
                            value={form.data.template}
                            onChange={(e) => form.setData('template', e.target.value)}
                        />
                        <InputError message={form.errors.template} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Publish new version
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AiPrompts({ templates, known_keys }: { templates: Template[]; known_keys: string[] }) {
    const { can } = usePermissions();
    const canManage = can('ai.prompts.manage');

    const groups = groupByKey(templates);
    const publishedKeys = groups.map((group) => group.key);
    const missingKeys = known_keys.filter((key) => !publishedKeys.includes(key));

    const activate = (key: string, version: number) => router.post(route('ai.prompts.activate'), { key, version }, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Prompts" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Prompt templates" description="Every prompt the platform sends, versioned — one version per key is active" />
                    {canManage && <PublishDialog knownKeys={known_keys} label="Publish version" />}
                </div>

                {missingKeys.length > 0 && (
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm font-medium">Keys with no published version</p>
                            <p className="text-muted-foreground text-sm">
                                These prompts fall back to the built-in default until a version is published: {missingKeys.join(', ')}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {groups.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No prompt versions published yet. Every feature runs on its built-in default until you publish one.
                    </p>
                ) : (
                    <div className="space-y-4">
                        {groups.map((group) => (
                            <div key={group.key}>
                                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <h3 className="font-mono text-sm font-medium">{group.key}</h3>
                                    {canManage && (
                                        <PublishDialog
                                            knownKeys={known_keys}
                                            label="New version"
                                            variant="secondary"
                                            size="sm"
                                            seed={{
                                                key: group.key,
                                                system: group.versions[0].system ?? '',
                                                template: group.versions[0].template,
                                                description: group.versions[0].description ?? '',
                                            }}
                                        />
                                    )}
                                </div>
                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="p-3 text-center">Version</th>
                                                <th className="p-3">Description</th>
                                                <th className="p-3">Content</th>
                                                <th className="p-3">State</th>
                                                {canManage && <th className="p-3 text-right">Actions</th>}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {group.versions.map((version) => (
                                                <tr key={version.id} className="hover:bg-muted/40 align-top">
                                                    <td className="p-3 text-center font-medium">v{version.version}</td>
                                                    <td className="text-muted-foreground p-3">{version.description ?? '—'}</td>
                                                    <td className="p-3">
                                                        <details>
                                                            <summary className="text-muted-foreground cursor-pointer">View prompt</summary>
                                                            <div className="mt-2 space-y-2">
                                                                <div>
                                                                    <p className="text-muted-foreground text-xs">System</p>
                                                                    <pre className="bg-muted mt-1 overflow-x-auto rounded-lg p-2 text-xs whitespace-pre-wrap">
                                                                        {version.system ?? '—'}
                                                                    </pre>
                                                                </div>
                                                                <div>
                                                                    <p className="text-muted-foreground text-xs">Template</p>
                                                                    <pre className="bg-muted mt-1 overflow-x-auto rounded-lg p-2 text-xs whitespace-pre-wrap">
                                                                        {version.template}
                                                                    </pre>
                                                                </div>
                                                            </div>
                                                        </details>
                                                    </td>
                                                    <td className="p-3">
                                                        <Badge variant={version.is_active ? 'default' : 'secondary'}>
                                                            {version.is_active ? 'Active' : 'Archived'}
                                                        </Badge>
                                                    </td>
                                                    {canManage && (
                                                        <td className="p-3">
                                                            <div className="flex justify-end">
                                                                {version.is_active ? (
                                                                    <span className="text-muted-foreground text-sm">In use</span>
                                                                ) : (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() => activate(group.key, version.version)}
                                                                    >
                                                                        Activate
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    )}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
