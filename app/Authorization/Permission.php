<?php

namespace App\Authorization;

/**
 * The permission registry — the single source of truth for authorization
 * (ADR-0002). Naming is `resource.action`. This list grows per module; each
 * new module adds its own permissions here and maps them onto roles in
 * {@see RolePermissions}. Enforcement is via Laravel's Gate; the frontend
 * receives the resolved list for UX gating only, never as a security boundary.
 */
enum Permission: string
{
    // Organization
    case OrganizationView = 'organization.view';
    case OrganizationUpdate = 'organization.update';
    case OrganizationDelete = 'organization.delete';

    // Members / memberships
    case MembersView = 'members.view';
    case MembersInvite = 'members.invite';
    case MembersUpdate = 'members.update';
    case MembersRemove = 'members.remove';

    // Teams
    case TeamsView = 'teams.view';
    case TeamsManage = 'teams.manage';

    // Roles & audit
    case RolesView = 'roles.view';
    case AuditView = 'audit.view';

    // Billing
    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';

    // Files
    case FilesView = 'files.view';
    case FilesManage = 'files.manage';

    // CRM
    case CrmContactRead = 'crm.contact.read';
    case CrmContactCreate = 'crm.contact.create';
    case CrmContactUpdate = 'crm.contact.update';
    case CrmContactDelete = 'crm.contact.delete';
    case CrmCompanyRead = 'crm.company.read';
    case CrmCompanyCreate = 'crm.company.create';
    case CrmCompanyUpdate = 'crm.company.update';
    case CrmCompanyDelete = 'crm.company.delete';
    case CrmLeadRead = 'crm.lead.read';
    case CrmLeadCreate = 'crm.lead.create';
    case CrmLeadUpdate = 'crm.lead.update';
    case CrmLeadDelete = 'crm.lead.delete';
    case CrmDealRead = 'crm.deal.read';
    case CrmDealCreate = 'crm.deal.create';
    case CrmDealUpdate = 'crm.deal.update';
    case CrmDealDelete = 'crm.deal.delete';
    case CrmActivityManage = 'crm.activity.manage';
    case CrmImport = 'crm.import';

    // Integrations
    case IntegrationsView = 'integrations.view';
    case IntegrationsManage = 'integrations.manage';

    // Marketing
    case MarketingView = 'marketing.view';
    case MarketingListsManage = 'marketing.lists.manage';
    case MarketingFormsManage = 'marketing.forms.manage';
    case MarketingCampaignsManage = 'marketing.campaigns.manage';
    case MarketingCampaignsSend = 'marketing.campaigns.send';
    case MarketingAutomationManage = 'marketing.automation.manage';
    case MarketingFunnelsView = 'marketing.funnels.view';

    // SEO & search intelligence
    case SeoView = 'seo.view';
    case SeoAuditsManage = 'seo.audits.manage';
    case SeoKeywordsManage = 'seo.keywords.manage';
    case SeoLocalManage = 'seo.local.manage';
    case SeoAiManage = 'seo.ai.manage';

    // Advertising
    case AdsView = 'ads.view';
    case AdsCampaignsManage = 'ads.campaigns.manage';
    case AdsRetargetingManage = 'ads.retargeting.manage';

    // Content & authority
    case ContentView = 'content.view';
    case ContentPiecesManage = 'content.pieces.manage';
    case ContentSocialManage = 'content.social.manage';
    case ContentReputationManage = 'content.reputation.manage';
    case ContentOutreachManage = 'content.outreach.manage';

    // Sales
    case SalesView = 'sales.view';
    case SalesScoringManage = 'sales.scoring.manage';
    case SalesAlertsManage = 'sales.alerts.manage';
    case SalesBookingManage = 'sales.booking.manage';
    case SalesEnablementManage = 'sales.enablement.manage';
    case SalesAccountsManage = 'sales.accounts.manage';

    // Analytics
    case AnalyticsView = 'analytics.view';
    case AnalyticsCallsManage = 'analytics.calls.manage';
    case AnalyticsExperimentsManage = 'analytics.experiments.manage';
    case AnalyticsCompetitorsManage = 'analytics.competitors.manage';
    case AnalyticsGrowthScoreManage = 'analytics.growth-score.manage';

    // AI
    case AiView = 'ai.view';
    case AiAgentUse = 'ai.agent.use';
    case AiActionsApprove = 'ai.actions.approve';
    case AiPromptsManage = 'ai.prompts.manage';

    // Platform administration (Stage 13) — held by platform staff.
    case AdminPlatform = 'admin.platform';
    case AdminImpersonate = 'admin.impersonate';

    // Support
    case SupportView = 'support.view';
    case SupportManage = 'support.manage';

    // Project delivery
    case ProjectsView = 'projects.view';
    case ProjectsManage = 'projects.manage';
    case ProjectsApprove = 'projects.approve';

    // Strategy / brand / training / performance workspaces
    case StrategyView = 'strategy.view';
    case StrategyManage = 'strategy.manage';

    // Client portal
    case PortalAccess = 'portal.access';

    // Website platform, service lines, verticals and locations (Stage 15)
    case WebView = 'web.view';
    case WebPagesManage = 'web.pages.manage';
    case WebTaxonomyManage = 'web.taxonomy.manage';

    // Website Chat / Conversations
    case ChatView = 'chat.view';
    case ChatInboxHandle = 'chat.inbox.handle';
    case ChatConversationsAssign = 'chat.conversations.assign';
    case ChatWidgetManage = 'chat.widget.manage';
    case ChatSettingsManage = 'chat.settings.manage';

    // Settings
    case SettingsManage = 'settings.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
