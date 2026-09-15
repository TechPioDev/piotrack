import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

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

    const menu = (
        <SidebarMenu>
            {items.map((item) => (
                <SidebarMenuItem key={item.url}>
                    <SidebarMenuButton asChild isActive={item.url === activeUrl} tooltip={label ? `${label} · ${item.title}` : item.title}>
                        <Link href={item.url} prefetch>
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            ))}
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
                        {label}
                        <ChevronRight className="transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent className="collapsible-content">{menu}</CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
