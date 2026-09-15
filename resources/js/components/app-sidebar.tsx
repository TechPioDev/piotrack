import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { DASHBOARD, navigationSections } from '@/lib/navigation';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export function AppSidebar() {
    const { can } = usePermissions();
    const sections = navigationSections(can);

    // A header exists to group things. A section holding a single item is not a
    // group, so it joins Dashboard as a plain link rather than costing a click
    // to reveal one child - Portal is the case that made this obvious.
    const groups = sections.filter((section) => section.items.length > 1);
    const topLevelItems = [DASHBOARD, ...sections.filter((section) => section.items.length === 1).flatMap((section) => section.items)];

    // The current path, without the query string - Inertia's page.url carries one.
    const path = usePage().url.split('?')[0];

    // Longest match wins, so /seo/keywords highlights Keywords rather than also
    // lighting up the /seo dashboard that happens to be a prefix of it.
    const activeUrl =
        [...groups.flatMap((group) => group.items), ...topLevelItems]
            .filter((item) => path === item.url || path.startsWith(`${item.url}/`))
            .sort((a, b) => b.url.length - a.url.length)[0]?.url ?? null;

    const activeGroupId = groups.find((group) => group.items.some((item) => item.url === activeUrl))?.id ?? null;

    const [openGroupId, setOpenGroupId] = useState<string | null>(activeGroupId);

    // Follow the page: arriving in a section from a link, the command palette or a
    // deep link opens that section. Manual toggles in between are left alone.
    useEffect(() => {
        if (activeGroupId) {
            setOpenGroupId(activeGroupId);
        }
    }, [activeGroupId]);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <OrganizationSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={topLevelItems} activeUrl={activeUrl} />
                {groups.map((group) => (
                    <NavMain
                        key={group.id}
                        items={group.items}
                        label={group.label}
                        activeUrl={activeUrl}
                        open={openGroupId === group.id}
                        onOpenChange={(open) => setOpenGroupId(open ? group.id : null)}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
