import { Breadcrumbs } from '@/components/breadcrumbs';
import { CommandPalette } from '@/components/command-palette';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { withSection } from '@/lib/navigation';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { notifications } = usePage<SharedData>().props;
    const { can } = usePermissions();
    const trail = withSection(breadcrumbs, usePage().url.split('?')[0], can);
    const unread = notifications?.unread ?? 0;

    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center justify-between gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={trail} />
            </div>
            <div className="flex items-center gap-2">
                <CommandPalette />
                <Link
                    href="/settings/notifications"
                    className="hover:bg-muted relative rounded-md p-2"
                    aria-label={`Notifications${unread > 0 ? ` (${unread} unread)` : ''}`}
                >
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span className="bg-primary text-primary-foreground absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px]">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Link>
            </div>
        </header>
    );
}
