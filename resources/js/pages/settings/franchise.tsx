import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Franchise', href: '/settings/franchise' }];

type ChildRow = {
    id: number;
    name: string;
    contacts: number;
    sqls: number;
    won_value: number;
    published_pages: number;
};

type Props = {
    parent: { name: string } | null;
    children: ChildRow[];
    linkable: { id: number; name: string }[];
    isOwner: boolean;
};

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`;

export default function Franchise({ parent, children, linkable, isOwner }: Props) {
    const { can } = usePermissions();
    const canManage = can('organization.update');
    const form = useForm<{ organization_id: string }>({ organization_id: '' });

    const link: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('franchise.link'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Franchise" />
            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        title="Franchise"
                        description="Link franchisee organizations under this one for roll-up reporting and shared brand assets"
                    />

                    {parent && (
                        <Card>
                            <CardContent className="p-4">
                                <p className="text-sm">
                                    This organization is a franchisee of <span className="font-medium">{parent.name}</span>. The franchisor sees
                                    roll-up totals and can push its brand profile here.
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {canManage && isOwner && linkable.length > 0 && (
                        <Card>
                            <CardContent className="p-4">
                                <form onSubmit={link} className="flex flex-wrap items-end gap-2">
                                    <div className="grid min-w-64 gap-1">
                                        <Label>Link an organization you own</Label>
                                        <Select value={form.data.organization_id} onValueChange={(v) => form.setData('organization_id', v)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Choose an organization" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {linkable.map((org) => (
                                                    <SelectItem key={org.id} value={String(org.id)}>
                                                        {org.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={form.errors.organization_id} />
                                    </div>
                                    <Button type="submit" disabled={form.processing || form.data.organization_id === ''}>
                                        Link as franchisee
                                    </Button>
                                </form>
                                <p className="text-muted-foreground mt-2 text-xs">
                                    Linking requires owning both organizations — it grants this one visibility into the franchisee's totals.
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    <div>
                        <h3 className="mb-2 text-sm font-medium">Franchisees</h3>
                        {children.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No franchisees linked{parent ? ' (a franchisee cannot take franchisees of its own)' : ''}.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Organization</th>
                                            <th className="p-3 text-center font-medium">Contacts</th>
                                            <th className="p-3 text-center font-medium">SQLs</th>
                                            <th className="p-3 text-center font-medium">Won value</th>
                                            <th className="p-3 text-center font-medium">Published pages</th>
                                            {canManage && <th className="p-3 font-medium">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {children.map((child) => (
                                            <tr key={child.id} className="hover:bg-muted/40">
                                                <td className="p-3 font-medium">{child.name}</td>
                                                <td className="p-3 text-center">{child.contacts}</td>
                                                <td className="p-3 text-center">{child.sqls}</td>
                                                <td className="p-3 text-center">{money(child.won_value)}</td>
                                                <td className="p-3 text-center">
                                                    <Badge variant={child.published_pages > 0 ? 'default' : 'outline'}>{child.published_pages}</Badge>
                                                </td>
                                                {canManage && (
                                                    <td className="space-x-2 p-3">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(route('franchise.push', child.id), {}, { preserveScroll: true })
                                                            }
                                                        >
                                                            Push brand
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() =>
                                                                router.delete(route('franchise.unlink', child.id), { preserveScroll: true })
                                                            }
                                                        >
                                                            Unlink
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
