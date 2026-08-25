import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { type NavItem } from '@/types';
import { usePage } from '@inertiajs/react';
import {
    Bell,
    BookOpen,
    Bot,
    Building2,
    CalendarClock,
    Code,
    Compass,
    Eye,
    FileCode,
    FileSearch,
    FileText,
    Filter,
    Flag,
    Flame,
    FlaskConical,
    Gauge,
    GitBranch,
    Globe,
    Handshake,
    KeyRound,
    Layers,
    LayoutGrid,
    LayoutTemplate,
    LifeBuoy,
    LineChart,
    List,
    Mail,
    MapPin,
    Megaphone,
    MessagesSquare,
    Palette,
    PenLine,
    Phone,
    Radar,
    Scale,
    Search,
    Send,
    Server,
    Share2,
    ShieldCheck,
    Sparkles,
    Star,
    Swords,
    Target,
    TrendingUp,
    Trophy,
    UserPlus,
    Users,
    Workflow,
} from 'lucide-react';
import { useEffect, useState } from 'react';

type NavGroup = { id: string; label: string; items: NavItem[] };

export function AppSidebar() {
    const { can } = usePermissions();

    const mainNavItems: NavItem[] = [{ title: 'Dashboard', url: '/dashboard', icon: LayoutGrid }];

    const crmNavItems: NavItem[] = [
        can('crm.contact.read') && { title: 'Contacts', url: '/crm/contacts', icon: Users },
        can('crm.company.read') && { title: 'Companies', url: '/crm/companies', icon: Building2 },
        can('crm.lead.read') && { title: 'Leads', url: '/crm/leads', icon: UserPlus },
        can('crm.deal.read') && { title: 'Deals', url: '/crm/deals', icon: Handshake },
    ].filter(Boolean) as NavItem[];

    const marketingNavItems: NavItem[] = can('marketing.view')
        ? [
              { title: 'Marketing', url: '/marketing', icon: Megaphone },
              { title: 'Lists', url: '/marketing/lists', icon: List },
              { title: 'Forms', url: '/marketing/forms', icon: FileText },
              { title: 'Landing Pages', url: '/marketing/landing-pages', icon: LayoutTemplate },
              { title: 'Campaigns', url: '/marketing/campaigns', icon: Mail },
              { title: 'Automation', url: '/marketing/automation', icon: Workflow },
              { title: 'Funnels', url: '/marketing/funnels', icon: Filter },
          ]
        : [];

    const seoNavItems: NavItem[] = [
        can('seo.view') && { title: 'Dashboard', url: '/seo', icon: Search },
        can('seo.view') && { title: 'Audit', url: '/seo/audits', icon: FileSearch },
        can('seo.view') && { title: 'Keywords', url: '/seo/keywords', icon: KeyRound },
        can('seo.view') && { title: 'Local', url: '/seo/local', icon: MapPin },
        can('seo.view') && { title: 'AI Visibility', url: '/seo/ai-visibility', icon: Sparkles },
        can('seo.view') && { title: 'Schema', url: '/seo/schema', icon: Code },
    ].filter(Boolean) as NavItem[];

    const adsNavItems: NavItem[] = [
        can('ads.view') && { title: 'Dashboard', url: '/ads', icon: Megaphone },
        can('ads.view') && { title: 'Campaigns', url: '/ads/campaigns', icon: Target },
        can('ads.view') && { title: 'Retargeting', url: '/ads/retargeting', icon: Users },
    ].filter(Boolean) as NavItem[];

    const contentNavItems: NavItem[] = [
        can('content.view') && { title: 'Dashboard', url: '/content', icon: PenLine },
        can('content.view') && { title: 'Content', url: '/content/pieces', icon: FileText },
        can('content.view') && { title: 'Social', url: '/content/social', icon: Share2 },
        can('content.view') && { title: 'Reputation', url: '/content/reputation', icon: Star },
        can('content.view') && { title: 'Outreach', url: '/content/outreach', icon: Send },
    ].filter(Boolean) as NavItem[];

    const chatNavItems: NavItem[] = [
        can('chat.view') && { title: 'Conversations', url: '/chat', icon: MessagesSquare },
        can('chat.view') && { title: 'Widgets', url: '/chat/widgets', icon: Code },
        can('chat.view') && { title: 'Analytics', url: '/chat/analytics', icon: LineChart },
    ].filter(Boolean) as NavItem[];

    const websiteNavItems: NavItem[] = [
        can('web.view') && { title: 'Pages', url: '/website', icon: Globe },
        can('web.view') && { title: 'Taxonomy & Locations', url: '/website/taxonomy', icon: MapPin },
    ].filter(Boolean) as NavItem[];

    const salesNavItems: NavItem[] = [
        can('sales.view') && { title: 'Dashboard', url: '/sales', icon: Gauge },
        can('sales.view') && { title: 'Scoring', url: '/sales/scoring', icon: Flame },
        can('sales.view') && { title: 'Intent', url: '/sales/intent', icon: Radar },
        can('sales.view') && { title: 'Alerts', url: '/sales/alerts', icon: Bell },
        can('sales.view') && { title: 'Booking', url: '/sales/booking', icon: CalendarClock },
        can('sales.view') && { title: 'Enablement', url: '/sales/enablement', icon: BookOpen },
        can('sales.view') && { title: 'Accounts', url: '/sales/accounts', icon: Building2 },
    ].filter(Boolean) as NavItem[];

    const analyticsNavItems: NavItem[] = [
        can('analytics.view') && { title: 'Dashboard', url: '/analytics', icon: LineChart },
        can('analytics.view') && { title: 'Attribution', url: '/analytics/attribution', icon: GitBranch },
        can('analytics.view') && { title: 'Growth Score', url: '/analytics/growth-score', icon: TrendingUp },
        can('analytics.view') && { title: 'Benchmarks', url: '/analytics/benchmarks', icon: Scale },
        can('analytics.view') && { title: 'Omnichannel', url: '/analytics/omnichannel', icon: Layers },
        can('analytics.view') && { title: 'Calls', url: '/analytics/calls', icon: Phone },
        can('analytics.view') && { title: 'Experiments', url: '/analytics/experiments', icon: FlaskConical },
        can('analytics.view') && { title: 'Competitors', url: '/analytics/competitors', icon: Swords },
    ].filter(Boolean) as NavItem[];

    const aiNavItems: NavItem[] = [
        can('ai.view') && { title: 'Dashboard', url: '/ai', icon: Sparkles },
        can('ai.view') && { title: 'Agent', url: '/ai/agent', icon: Bot },
        can('ai.view') && { title: 'Conversations', url: '/ai/conversations', icon: MessagesSquare },
        can('ai.view') && { title: 'Approvals', url: '/ai/actions', icon: ShieldCheck },
        can('ai.view') && { title: 'Prompts', url: '/ai/prompts', icon: FileCode },
        can('ai.view') && { title: 'Visibility', url: '/ai/visibility', icon: Eye },
    ].filter(Boolean) as NavItem[];

    const deliveryNavItems: NavItem[] = [
        can('projects.view') && { title: 'Projects', url: '/projects', icon: Workflow },
        can('support.view') && { title: 'Support', url: '/support', icon: LifeBuoy },
        can('strategy.view') && { title: 'Strategy', url: '/strategy', icon: Compass },
        can('strategy.view') && { title: 'Brand', url: '/strategy/brand', icon: Palette },
        can('strategy.view') && { title: 'Performance', url: '/strategy/performance', icon: Trophy },
    ].filter(Boolean) as NavItem[];

    const portalNavItems: NavItem[] = [can('portal.access') && { title: 'Client Portal', url: '/portal', icon: Handshake }].filter(
        Boolean,
    ) as NavItem[];

    const platformNavItems: NavItem[] = [
        can('admin.platform') && { title: 'Overview', url: '/platform', icon: Server },
        can('admin.platform') && { title: 'Feature Flags', url: '/platform/flags', icon: Flag },
        can('admin.platform') && { title: 'Announcements', url: '/platform/announcements', icon: Megaphone },
        can('admin.platform') && { title: 'AI Provider', url: '/platform/ai', icon: Sparkles },
    ].filter(Boolean) as NavItem[];

    const sections: NavGroup[] = [
        { id: 'crm', label: 'CRM', items: crmNavItems },
        { id: 'marketing', label: 'Marketing', items: marketingNavItems },
        { id: 'seo', label: 'SEO', items: seoNavItems },
        { id: 'ads', label: 'Advertising', items: adsNavItems },
        { id: 'content', label: 'Content', items: contentNavItems },
        { id: 'chat', label: 'Website Chat', items: chatNavItems },
        { id: 'website', label: 'Website', items: websiteNavItems },
        { id: 'sales', label: 'Sales', items: salesNavItems },
        { id: 'analytics', label: 'Analytics', items: analyticsNavItems },
        { id: 'ai', label: 'AI', items: aiNavItems },
        { id: 'delivery', label: 'Delivery', items: deliveryNavItems },
        { id: 'portal', label: 'Portal', items: portalNavItems },
        { id: 'platform', label: 'Platform', items: platformNavItems },
    ].filter((section) => section.items.length > 0);

    // A header exists to group things. A section holding a single item is not a
    // group, so it joins Dashboard as a plain link rather than costing a click
    // to reveal one child - Portal is the case that made this obvious.
    const groups = sections.filter((section) => section.items.length > 1);
    const topLevelItems = [...mainNavItems, ...sections.filter((section) => section.items.length === 1).flatMap((section) => section.items)];

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
