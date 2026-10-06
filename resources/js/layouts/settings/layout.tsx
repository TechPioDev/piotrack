import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/hooks/use-permissions';
import { usePlanLocks } from '@/hooks/use-plan-locks';
import { settingsItems } from '@/lib/navigation';
import { NOT_IN_PLAN } from '@/lib/plan-areas';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const { can } = usePermissions();

    // Organization settings are permission-gated (RBAC-005); the list is shared
    // with the command palette so every settings page is findable by name.
    // A page the plan leaves out (Teams, the audit log) is marked, not hidden.
    const sidebarNavItems = settingsItems(can, usePlanLocks());

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
                                <Link href={item.url} prefetch={!item.locked} title={item.locked ? `${item.title} — ${NOT_IN_PLAN}` : undefined}>
                                    {item.title}
                                    {item.locked && (
                                        <>
                                            <Lock data-plan-lock className="text-muted-foreground ml-auto size-3" aria-hidden="true" />{' '}
                                            <span className="sr-only">({NOT_IN_PLAN})</span>
                                        </>
                                    )}
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
