import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { humanizeKey } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/settings/notifications' }];

type NotificationRow = {
    id: string;
    category: string | null;
    title: string;
    body: string;
    url: string | null;
    read_at: string | null;
    created_at: string;
};

type NotificationsProps = {
    notifications: NotificationRow[];
    preferences: Record<string, Record<string, boolean>>;
    categories: string[];
    channels: string[];
};

export default function Notifications({ notifications, preferences, categories, channels }: NotificationsProps) {
    const hasUnread = notifications.some((n) => !n.read_at);

    const togglePref = (category: string, channel: string, enabled: boolean) => {
        router.patch(route('notifications.preferences'), { category, channel, enabled }, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />

            <SettingsLayout>
                <div className="space-y-8">
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <HeadingSmall title="Notifications" description="Your recent notifications" />
                            {hasUnread && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}
                                >
                                    Mark all read
                                </Button>
                            )}
                        </div>

                        {notifications.length === 0 ? (
                            <p className="text-muted-foreground text-sm">You're all caught up — no notifications yet.</p>
                        ) : (
                            <ul className="divide-y rounded-lg border">
                                {notifications.map((n) => (
                                    <li key={n.id} className={`p-4 ${n.read_at ? '' : 'bg-muted/40'}`}>
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="flex items-center gap-2 font-medium">
                                                    {n.title}
                                                    {n.category && <Badge variant="outline">{n.category}</Badge>}
                                                    {!n.read_at && <span className="bg-primary size-2 rounded-full" />}
                                                </p>
                                                <p className="text-muted-foreground text-sm">{n.body}</p>
                                                <p className="text-muted-foreground mt-1 text-xs">{new Date(n.created_at).toLocaleString()}</p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                {n.url && (
                                                    <Button asChild variant="ghost" size="sm">
                                                        <Link
                                                            href={n.url}
                                                            onClick={() =>
                                                                router.post(route('notifications.read', n.id), {}, { preserveScroll: true })
                                                            }
                                                        >
                                                            View
                                                        </Link>
                                                    </Button>
                                                )}
                                                {!n.read_at && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => router.post(route('notifications.read', n.id), {}, { preserveScroll: true })}
                                                    >
                                                        Mark read
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="space-y-4">
                        <HeadingSmall title="Preferences" description="Choose how you're notified. Security alerts are always sent." />
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Category</th>
                                        {channels.map((c) => (
                                            <th key={c} className="p-3 text-center font-medium">
                                                {humanizeKey(c)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {categories.map((category) => (
                                        <tr key={category}>
                                            <td className="p-3 font-medium">{humanizeKey(category)}</td>
                                            {channels.map((channel) => {
                                                // Security in-app/email cannot be disabled; SMS stays opt-in everywhere.
                                                const locked = category === 'security' && channel !== 'sms';
                                                return (
                                                    <td key={channel} className="p-3 text-center">
                                                        <Checkbox
                                                            checked={locked ? true : (preferences[category]?.[channel] ?? true)}
                                                            disabled={locked}
                                                            onCheckedChange={(v) => togglePref(category, channel, Boolean(v))}
                                                        />
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
