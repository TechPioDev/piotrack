import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';

const accountNavItems: NavItem[] = [
    { title: 'Profile', url: '/settings/profile', icon: null },
    { title: 'Password', url: '/settings/password', icon: null },
    { title: 'Two-factor auth', url: '/settings/two-factor', icon: null },
    { title: 'API tokens', url: '/settings/api-tokens', icon: null },
    { title: 'Notifications', url: '/settings/notifications', icon: null },
    { title: 'Appearance', url: '/settings/appearance', icon: null },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const { can } = usePermissions();

    // Organization settings are permission-gated (RBAC-005).
    const organizationNavItems: NavItem[] = [
        can('organization.view') && { title: 'Organization', url: '/settings/organization', icon: null },
        can('organization.view') && { title: 'Franchise', url: '/settings/franchise', icon: null },
        can('members.view') && { title: 'Members', url: '/settings/members', icon: null },
        can('teams.view') && { title: 'Teams', url: '/settings/teams', icon: null },
        can('billing.view') && { title: 'Billing', url: '/billing', icon: null },
        can('files.view') && { title: 'Files', url: '/settings/files', icon: null },
        can('integrations.view') && { title: 'Integrations', url: '/settings/integrations', icon: null },
        can('audit.view') && { title: 'Audit log', url: '/settings/audit-log', icon: null },
    ].filter(Boolean) as NavItem[];

    const sidebarNavItems: NavItem[] = [...organizationNavItems, ...accountNavItems];

    const currentPath = window.location.pathname;

    return (
        <div className="px-4 py-6">
            <Heading title="Settings" description="Manage your profile and account settings" />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {sidebarNavItems.map((item) => (
                            <Button
                                key={item.url}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': currentPath === item.url,
                                })}
                            >
                                <Link href={item.url} prefetch>
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 md:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
