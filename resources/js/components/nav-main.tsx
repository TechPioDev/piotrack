import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { NOT_IN_PLAN } from '@/lib/plan-areas';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronRight, Lock } from 'lucide-react';

/**
 * One section of the sidebar.
 *
 * With a `label` it renders as a collapsible section; the parent decides which
 * one is open, so only a single section is expanded at a time. Without a label
 * (the standalone Dashboard link) it renders flat, since a lone item does not
 * earn a header of its own.
 *
 * When the sidebar is collapsed to the icon rail there is no room for headers,
 * so every item stays visible and reachable by its icon, and its tooltip names
 * the section ("SEO · Overview") that the missing header would have.
 *
 * A page the workspace's plan leaves out carries a small lock, and says so in
 * its tooltip and to a screen reader. It is still a link: the page it leads to
 * explains what the plan lacks and where to change it. A section whose pages
 * are all left out wears the lock on its header too, so nobody has to open it
 * to find that out.
 */
export function NavMain({
    items = [],
    label,
    activeUrl = null,
    open = false,
    onOpenChange,
}: {
    items: NavItem[];
    label?: string;
    activeUrl?: string | null;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const { state, isMobile } = useSidebar();
    const isIconRail = state === 'collapsed' && !isMobile;
    const allLocked = items.length > 0 && items.every((item) => item.locked);

    const menu = (
        <SidebarMenu>
            {items.map((item) => {
                const name = label ? `${label} · ${item.title}` : item.title;

                return (
                    <SidebarMenuItem key={item.url}>
                        <SidebarMenuButton asChild isActive={item.url === activeUrl} tooltip={item.locked ? `${name} — ${NOT_IN_PLAN}` : name}>
                            <Link href={item.url} prefetch={!item.locked}>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                                {item.locked && (
                                    <>
                                        <Lock
                                            data-plan-lock
                                            className="text-muted-foreground ml-auto !size-3 group-data-[collapsible=icon]:hidden"
                                            aria-hidden="true"
                                        />{' '}
                                        <span className="sr-only">({NOT_IN_PLAN})</span>
                                    </>
                                )}
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                );
            })}
        </SidebarMenu>
    );

    if (!label || isIconRail) {
        return (
            <SidebarGroup className="px-2 py-0">
                {label && !isIconRail && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
                {menu}
            </SidebarGroup>
        );
    }

    return (
        <Collapsible open={open} onOpenChange={onOpenChange} className="group/collapsible">
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel asChild>
                    <CollapsibleTrigger className="hover:text-sidebar-foreground w-full cursor-pointer justify-between">
                        <span className="flex items-center gap-1.5">
                            {label}
                            {allLocked && (
                                <>
                                    <Lock data-plan-lock className="!size-3" aria-hidden="true" /> <span className="sr-only">({NOT_IN_PLAN})</span>
                                </>
                            )}
                        </span>
                        <ChevronRight className="transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent className="collapsible-content">{menu}</CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
