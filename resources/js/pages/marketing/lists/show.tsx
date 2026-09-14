import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

type MarketingList = {
    id: number;
    name: string;
    description: string | null;
    type: string;
    criteria: Record<string, unknown> | null;
    member_count: number;
};

type Member = {
    id: number;
    name: string;
    email: string | null;
    lifecycle_stage: string | null;
};

export default function ListShow({ list, members }: { list: MarketingList; members: Member[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Lists', href: '/marketing/lists' },
        { title: list.name, href: `/marketing/lists/${list.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={list.name} />
            <div className="space-y-4 p-4">
                <div className="flex items-center gap-2">
                    <Heading title={list.name} description={list.description ?? undefined} />
                    <Badge variant="outline">{list.type}</Badge>
                </div>

                <Card>
                    <CardContent className="p-4 text-sm">
                        <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">Members</span>
                            <span className="font-medium">{list.member_count}</span>
                        </div>
                    </CardContent>
                </Card>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Members</h3>
                    {members.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No members yet. Contacts matching this list will appear here.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Name</th>
                                        <th className="p-3">Email</th>
                                        <th className="p-3">Lifecycle stage</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {members.map((member) => (
                                        <tr key={member.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{member.name}</td>
                                            <td className="text-muted-foreground p-3">{member.email ?? '—'}</td>
                                            <td className="p-3">
                                                {member.lifecycle_stage ? <Badge variant="secondary">{member.lifecycle_stage}</Badge> : '—'}
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
