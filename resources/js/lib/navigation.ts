import { type BreadcrumbItem, type NavItem } from '@/types';
import {
    Activity,
    Bell,
    BookOpen,
    Bot,
    Braces,
    Building2,
    CalendarClock,
    ChartColumn,
    CircleDollarSign,
    Code,
    Compass,
    Cpu,
    Crosshair,
    DoorOpen,
    Eye,
    FileCode,
    FileSearch,
    FileText,
    Filter,
    Flag,
    Flame,
    FlaskConical,
    FolderKanban,
    Gauge,
    GitBranch,
    Globe,
    GraduationCap,
    Handshake,
    KeyRound,
    Landmark,
    Layers,
    LayoutGrid,
    LayoutTemplate,
    LifeBuoy,
    LineChart,
    Link2,
    List,
    Mail,
    MapPin,
    Megaphone,
    MessageSquareText,
    MessagesSquare,
    Microscope,
    MousePointerClick,
    Network,
    Newspaper,
    Palette,
    PenLine,
    Phone,
    Radar,
    Radio,
    Scale,
    ScanSearch,
    Search,
    Send,
    Server,
    Share2,
    ShieldCheck,
    Sparkles,
    Star,
    Swords,
    Tags,
    Target,
    Telescope,
    TrendingUp,
    Trophy,
    UserPlus,
    Users,
    Workflow,
} from 'lucide-react';

/**
 * The one definition of where things live (UI-P5). The sidebar, the header
 * breadcrumb, the settings menu and the command palette all read it, so a page
 * is named, grouped and permission-gated identically everywhere.
 *
 * Every item in the sidebar has its own icon: collapsed to the icon rail, the
 * icon is all a person has to go on.
 */

export type NavSection = { id: string; label: string; items: NavItem[] };

type Can = (permission: string) => boolean;

type Entry = NavItem & { permission?: string };

const SECTIONS: { id: string; label: string; items: Entry[] }[] = [
    {
        id: 'crm',
        label: 'CRM',
        items: [
            { title: 'Contacts', url: '/crm/contacts', icon: Users, permission: 'crm.contact.read', keywords: 'people' },
            { title: 'Companies', url: '/crm/companies', icon: Building2, permission: 'crm.company.read', keywords: 'accounts organizations' },
            { title: 'Leads', url: '/crm/leads', icon: UserPlus, permission: 'crm.lead.read' },
            { title: 'Deals', url: '/crm/deals', icon: Handshake, permission: 'crm.deal.read', keywords: 'pipeline opportunities kanban' },
        ],
    },
    {
        id: 'marketing',
        label: 'Marketing',
        items: [
            { title: 'Overview', url: '/marketing', icon: Megaphone, permission: 'marketing.view', keywords: 'marketing dashboard' },
            { title: 'Lists', url: '/marketing/lists', icon: List, permission: 'marketing.view', keywords: 'segments audiences' },
            { title: 'Forms', url: '/marketing/forms', icon: FileText, permission: 'marketing.view' },
            { title: 'Landing Pages', url: '/marketing/landing-pages', icon: LayoutTemplate, permission: 'marketing.view' },
            { title: 'Campaigns', url: '/marketing/campaigns', icon: Mail, permission: 'marketing.view', keywords: 'email sms newsletter' },
            { title: 'Automation', url: '/marketing/automation', icon: Workflow, permission: 'marketing.view', keywords: 'workflows sequences' },
            { title: 'Funnels', url: '/marketing/funnels', icon: Filter, permission: 'marketing.view' },
        ],
    },
    {
        id: 'seo',
        label: 'SEO',
        items: [
            { title: 'Overview', url: '/seo', icon: Search, permission: 'seo.view', keywords: 'seo dashboard' },
            { title: 'Audit', url: '/seo/audits', icon: FileSearch, permission: 'seo.view', keywords: 'technical audits crawl' },
            { title: 'Keywords', url: '/seo/keywords', icon: KeyRound, permission: 'seo.view', keywords: 'rankings positions' },
            { title: 'Local', url: '/seo/local', icon: MapPin, permission: 'seo.view', keywords: 'local seo google business profile citations' },
            { title: 'Links', url: '/seo/links', icon: Link2, permission: 'seo.view', keywords: 'backlinks backlink audit disavow' },
            {
                title: 'Search Health',
                url: '/seo/health',
                icon: Activity,
                permission: 'seo.view',
                keywords: 'search console core web vitals penalty',
            },
            {
                title: 'AI answer checks',
                url: '/seo/ai-visibility',
                icon: ScanSearch,
                permission: 'seo.view',
                keywords: 'ai visibility chatgpt gemini perplexity cited sources',
            },
            { title: 'LLMO', url: '/seo/llmo', icon: Network, permission: 'seo.view', keywords: 'llm optimization aeo answer engine' },
            { title: 'Schema', url: '/seo/schema', icon: Braces, permission: 'seo.view', keywords: 'structured data json-ld' },
        ],
    },
    {
        id: 'ads',
        label: 'Advertising',
        items: [
            { title: 'Overview', url: '/ads', icon: CircleDollarSign, permission: 'ads.view', keywords: 'ads advertising dashboard ppc spend' },
            { title: 'Campaigns', url: '/ads/campaigns', icon: Target, permission: 'ads.view', keywords: 'ads ad campaigns google linkedin meta' },
            { title: 'Retargeting', url: '/ads/retargeting', icon: Crosshair, permission: 'ads.view', keywords: 'ads audiences remarketing' },
        ],
    },
    {
        id: 'content',
        label: 'Content',
        items: [
            { title: 'Overview', url: '/content', icon: PenLine, permission: 'content.view', keywords: 'content dashboard' },
            {
                title: 'Library',
                url: '/content/pieces',
                icon: Newspaper,
                permission: 'content.view',
                keywords: 'content library articles pieces blog',
            },
            { title: 'Social', url: '/content/social', icon: Share2, permission: 'content.view', keywords: 'posts linkedin' },
            { title: 'Reputation', url: '/content/reputation', icon: Star, permission: 'content.view', keywords: 'reviews' },
            { title: 'Outreach', url: '/content/outreach', icon: Send, permission: 'content.view', keywords: 'digital pr link building prospects' },
        ],
    },
    {
        id: 'chat',
        label: 'Website Chat',
        items: [
            { title: 'Conversations', url: '/chat', icon: MessagesSquare, permission: 'chat.view', keywords: 'chat inbox' },
            { title: 'Widgets', url: '/chat/widgets', icon: Code, permission: 'chat.view', keywords: 'chat widget embed' },
            { title: 'Analytics', url: '/chat/analytics', icon: ChartColumn, permission: 'chat.view', keywords: 'chat analytics' },
        ],
    },
    {
        id: 'website',
        label: 'Website',
        items: [
            { title: 'Pages', url: '/website', icon: Globe, permission: 'web.view', keywords: 'website pages builder' },
            {
                title: 'Taxonomy & Locations',
                url: '/website/taxonomy',
                icon: Tags,
                permission: 'web.view',
                keywords: 'service lines verticals branches',
            },
        ],
    },
    {
        id: 'sales',
        label: 'Sales',
        items: [
            { title: 'Overview', url: '/sales', icon: Gauge, permission: 'sales.view', keywords: 'sales dashboard' },
            { title: 'Scoring', url: '/sales/scoring', icon: Flame, permission: 'sales.view', keywords: 'lead scoring routing' },
            { title: 'Visitors', url: '/sales/visitors', icon: Eye, permission: 'sales.view', keywords: 'website visitors identification' },
            { title: 'Intent', url: '/sales/intent', icon: Radar, permission: 'sales.view', keywords: 'buyer intent signals' },
            { title: 'Alerts', url: '/sales/alerts', icon: Bell, permission: 'sales.view', keywords: 'sales alerts rules' },
            { title: 'Booking', url: '/sales/booking', icon: CalendarClock, permission: 'sales.view', keywords: 'meetings calendar appointments' },
            {
                title: 'Enablement',
                url: '/sales/enablement',
                icon: BookOpen,
                permission: 'sales.view',
                keywords: 'sales enablement assets proposals roi',
            },
            { title: 'Accounts', url: '/sales/accounts', icon: Landmark, permission: 'sales.view', keywords: 'target accounts abm' },
        ],
    },
    {
        id: 'analytics',
        label: 'Analytics',
        items: [
            { title: 'Overview', url: '/analytics', icon: LineChart, permission: 'analytics.view', keywords: 'analytics dashboard funnel' },
            {
                title: 'Attribution',
                url: '/analytics/attribution',
                icon: GitBranch,
                permission: 'analytics.view',
                keywords: 'revenue attribution roi',
            },
            { title: 'Growth Score', url: '/analytics/growth-score', icon: TrendingUp, permission: 'analytics.view', keywords: 'msp growth score' },
            { title: 'Benchmarks', url: '/analytics/benchmarks', icon: Scale, permission: 'analytics.view', keywords: 'peers' },
            { title: 'Omnichannel', url: '/analytics/omnichannel', icon: Layers, permission: 'analytics.view', keywords: 'channels journeys' },
            { title: 'Calls', url: '/analytics/calls', icon: Phone, permission: 'analytics.view', keywords: 'call tracking recordings' },
            { title: 'Experiments', url: '/analytics/experiments', icon: FlaskConical, permission: 'analytics.view', keywords: 'a/b tests' },
            {
                title: 'Behavior',
                url: '/analytics/behavior',
                icon: MousePointerClick,
                permission: 'analytics.view',
                keywords: 'heatmaps scroll bounce',
            },
            { title: 'Competitors', url: '/analytics/competitors', icon: Swords, permission: 'analytics.view', keywords: 'competitive intelligence' },
        ],
    },
    {
        id: 'ai',
        label: 'AI',
        items: [
            { title: 'Overview', url: '/ai', icon: Sparkles, permission: 'ai.view', keywords: 'ai dashboard credits usage' },
            { title: 'Agent', url: '/ai/agent', icon: Bot, permission: 'ai.view', keywords: 'ai sales agent research' },
            { title: 'Conversations', url: '/ai/conversations', icon: MessageSquareText, permission: 'ai.view', keywords: 'ai chat' },
            { title: 'Approvals', url: '/ai/actions', icon: ShieldCheck, permission: 'ai.view', keywords: 'ai actions pending' },
            { title: 'Prompts', url: '/ai/prompts', icon: FileCode, permission: 'ai.view', keywords: 'prompt templates' },
            {
                title: 'Visibility report',
                url: '/ai/visibility',
                icon: Telescope,
                permission: 'ai.view',
                keywords: 'ai visibility share of voice trend',
            },
        ],
    },
    {
        id: 'delivery',
        label: 'Delivery',
        items: [
            { title: 'Projects', url: '/projects', icon: FolderKanban, permission: 'projects.view', keywords: 'tasks sprints deliverables' },
            { title: 'Support', url: '/support', icon: LifeBuoy, permission: 'support.view', keywords: 'tickets helpdesk' },
            { title: 'Strategy', url: '/strategy', icon: Compass, permission: 'strategy.view', keywords: 'plans kpis' },
            { title: 'Research', url: '/strategy/research', icon: Microscope, permission: 'strategy.view', keywords: 'market sizing personas' },
            { title: 'Brand', url: '/strategy/brand', icon: Palette, permission: 'strategy.view', keywords: 'brand profile style guide' },
            { title: 'Training', url: '/strategy/training', icon: GraduationCap, permission: 'strategy.view', keywords: 'courses lms' },
            {
                title: 'Performance',
                url: '/strategy/performance',
                icon: Trophy,
                permission: 'strategy.view',
                keywords: 'performance agreements lead guarantee',
            },
        ],
    },
    {
        id: 'portal',
        label: 'Portal',
        items: [{ title: 'Client Portal', url: '/portal', icon: DoorOpen, permission: 'portal.access' }],
    },
    {
        id: 'platform',
        label: 'Platform',
        items: [
            { title: 'Overview', url: '/platform', icon: Server, permission: 'admin.platform', keywords: 'platform admin tenants' },
            { title: 'Feature Flags', url: '/platform/flags', icon: Flag, permission: 'admin.platform' },
            { title: 'Announcements', url: '/platform/announcements', icon: Radio, permission: 'admin.platform' },
            { title: 'AI Provider', url: '/platform/ai', icon: Cpu, permission: 'admin.platform' },
        ],
    },
];

const SETTINGS: Entry[] = [
    { title: 'Organization', url: '/settings/organization', permission: 'organization.view' },
    { title: 'Franchise', url: '/settings/franchise', permission: 'organization.view' },
    { title: 'Members', url: '/settings/members', permission: 'members.view', keywords: 'team users invite seats' },
    { title: 'Teams', url: '/settings/teams', permission: 'teams.view' },
    { title: 'Billing', url: '/billing', permission: 'billing.view', keywords: 'plan subscription invoices payment' },
    { title: 'Files', url: '/settings/files', permission: 'files.view' },
    { title: 'Integrations', url: '/settings/integrations', permission: 'integrations.view', keywords: 'webhooks slack teams zapier' },
    { title: 'Audit log', url: '/settings/audit-log', permission: 'audit.view' },
    { title: 'Profile', url: '/settings/profile' },
    { title: 'Password', url: '/settings/password' },
    { title: 'Two-factor auth', url: '/settings/two-factor', keywords: '2fa security' },
    { title: 'API tokens', url: '/settings/api-tokens', keywords: 'api keys' },
    { title: 'Notifications', url: '/settings/notifications', keywords: 'email sms preferences' },
    { title: 'Appearance', url: '/settings/appearance', keywords: 'theme dark mode' },
];

const allowed = (can: Can) => (entry: Entry) => entry.permission === undefined || can(entry.permission);
const strip = ({ title, url, icon, keywords }: Entry): NavItem => ({ title, url, icon, keywords });

export const DASHBOARD: NavItem = { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid, keywords: 'home command center' };

/** Sidebar sections the user may see, in sidebar order; empty sections are dropped. */
export function navigationSections(can: Can): NavSection[] {
    return SECTIONS.map((section) => ({ id: section.id, label: section.label, items: section.items.filter(allowed(can)).map(strip) })).filter(
        (section) => section.items.length > 0,
    );
}

/** The settings menu: organization settings first, then the user's own account. */
export function settingsItems(can: Can): NavItem[] {
    return SETTINGS.filter(allowed(can)).map(strip);
}

/** Everything a person can navigate to, each with the section it belongs to. */
export function navigablePages(can: Can): { section: string; item: NavItem }[] {
    return [
        { section: 'Dashboard', item: DASHBOARD },
        ...navigationSections(can).flatMap((section) => section.items.map((item) => ({ section: section.label, item }))),
        ...settingsItems(can).map((item) => ({ section: 'Settings', item })),
    ];
}

/**
 * Pages matching what someone typed, best first. Every word typed must start a
 * word in the page's section, name or keywords ("seo key" finds Keywords, "ads"
 * does not find Leads); a match on the page's own name outranks a keyword match.
 */
export function searchPages(query: string, pages: { section: string; item: NavItem }[], limit = 6): { section: string; item: NavItem }[] {
    const tokens = query.toLowerCase().split(/\s+/).filter(Boolean);
    if (tokens.length === 0) {
        return [];
    }

    const words = (text: string) =>
        text
            .toLowerCase()
            .split(/[^a-z0-9/]+/)
            .filter(Boolean);

    return pages
        .map((page, order) => {
            const titleWords = words(page.item.title);
            const allWords = [...words(page.section), ...titleWords, ...words(page.item.keywords ?? '')];
            if (!tokens.every((token) => allWords.some((word) => word.startsWith(token)))) {
                return null;
            }

            const score = page.item.title.toLowerCase().startsWith(query.trim().toLowerCase())
                ? 3
                : tokens.every((token) => titleWords.some((word) => word.startsWith(token)))
                  ? 2
                  : 1;

            return { page, score, order };
        })
        .filter((match): match is { page: { section: string; item: NavItem }; score: number; order: number } => match !== null)
        .sort((a, b) => b.score - a.score || a.order - b.order)
        .slice(0, limit)
        .map((match) => match.page);
}

/**
 * Where a path belongs: the longest item URL it equals or sits under, so
 * /seo/keywords belongs to Keywords rather than to the /seo overview.
 */
export function locate(path: string, can: Can): { section: string; sectionUrl: string; item: NavItem } | null {
    const candidates = [
        ...navigationSections(can).flatMap((section) =>
            section.items.map((item) => ({ section: section.label, sectionUrl: section.items[0].url, item })),
        ),
        ...settingsItems(can).map((item) => ({ section: 'Settings', sectionUrl: '/settings/profile', item })),
    ].filter(({ item }) => path === item.url || path.startsWith(`${item.url}/`));

    return candidates.sort((a, b) => b.item.url.length - a.item.url.length)[0] ?? null;
}

/**
 * Put the section in front of a page's own breadcrumb, so a header reads
 * "Advertising › Campaigns" instead of a "Campaigns" that could be either
 * campaigns page. A leading section word the page repeated ("AI Agent") is
 * dropped from its own crumb.
 */
export function withSection(breadcrumbs: BreadcrumbItem[], path: string, can: Can): BreadcrumbItem[] {
    const place = locate(path, can);
    if (place === null || breadcrumbs.length === 0) {
        return breadcrumbs;
    }

    const label = place.section.toLowerCase();
    const [first, ...rest] = breadcrumbs;
    if (first.title.toLowerCase() === label) {
        return breadcrumbs;
    }

    const repeated = first.title.toLowerCase().startsWith(`${label} `) ? first.title.slice(label.length + 1) : first.title;

    return [{ title: place.section, href: place.sectionUrl }, { ...first, title: repeated.charAt(0).toUpperCase() + repeated.slice(1) }, ...rest];
}
