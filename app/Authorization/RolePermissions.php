<?php

namespace App\Authorization;

/**
 * Maps each organization role to the permissions it grants within an
 * organization. Owner is intentionally granted every permission and also
 * receives a Gate bypass within its own organization. Platform roles hold no
 * org-scoped permissions (Super Admin bypasses globally); their platform
 * capabilities arrive with the platform admin area (Stage 13).
 *
 * This map grows as modules add permissions to {@see Permission}.
 */
class RolePermissions
{
    /**
     * @return array<string, list<Permission>>
     */
    public static function map(): array
    {
        // Platform-staff abilities are held only through users.platform_role and
        // must never be granted by an organization role. They are excluded from
        // the base set here so that Owner (all) and Admin (all but delete),
        // which are built from it, cannot sweep them in. A tenant owner or admin
        // otherwise passed the can:admin.platform gate and could open the
        // platform console - reading every tenant's name, plan, MRR and AI spend
        // - and toggle global feature flags. Platform super admins still get
        // these via Gate::before, which bypasses this map entirely.
        $platformOnly = [Permission::AdminPlatform, Permission::AdminImpersonate];

        $all = array_values(array_filter(
            Permission::cases(),
            fn (Permission $p) => ! in_array($p, $platformOnly, true),
        ));

        $adminExceptDelete = array_values(array_filter(
            $all,
            fn (Permission $p) => $p !== Permission::OrganizationDelete,
        ));

        // CRM permission groupings.
        $crmAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'crm.')));
        $crmReadWrite = [
            Permission::CrmContactRead, Permission::CrmContactCreate, Permission::CrmContactUpdate,
            Permission::CrmCompanyRead, Permission::CrmCompanyCreate, Permission::CrmCompanyUpdate,
            Permission::CrmLeadRead, Permission::CrmLeadCreate, Permission::CrmLeadUpdate,
            Permission::CrmDealRead, Permission::CrmDealCreate, Permission::CrmDealUpdate,
            Permission::CrmActivityManage,
        ];
        $crmReadOnly = [
            Permission::CrmContactRead, Permission::CrmCompanyRead,
            Permission::CrmLeadRead, Permission::CrmDealRead,
        ];

        // Marketing permission groupings.
        $marketingAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'marketing.')));
        // Marketing users can build everything but not blast a send.
        $marketingBuild = [
            Permission::MarketingView,
            Permission::MarketingListsManage,
            Permission::MarketingFormsManage,
            Permission::MarketingCampaignsManage,
            Permission::MarketingAutomationManage,
            Permission::MarketingFunnelsView,
        ];

        // SEO permission grouping.
        $seoAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'seo.')));

        // Advertising permission grouping.
        $adsAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'ads.')));

        // Content & authority permission grouping.
        $contentAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'content.')));

        // Sales permission grouping.
        $salesAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'sales.')));

        // Analytics permission grouping.
        $analyticsAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'analytics.')));

        // AI permission grouping.
        $aiAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'ai.')));

        // Service-delivery groupings (Stage 13). Platform administration is
        // deliberately NOT in any organization role — it is held by platform
        // staff through users.platform_role.
        $deliveryAll = [
            Permission::SupportView, Permission::SupportManage,
            Permission::ProjectsView, Permission::ProjectsManage, Permission::ProjectsApprove,
            Permission::StrategyView, Permission::StrategyManage,
        ];
        $deliveryReadOnly = [Permission::SupportView, Permission::ProjectsView, Permission::StrategyView];

        // Website platform + taxonomy (Stage 15).
        $webAll = [Permission::WebView, Permission::WebPagesManage, Permission::WebTaxonomyManage];

        // Website Chat groupings. Managers get everything; reps/marketing users
        // work the inbox (and marketing users build widgets); read-only roles view.
        $chatAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'chat.')));
        $chatWork = [Permission::ChatView, Permission::ChatInboxHandle, Permission::ChatConversationsAssign];

        return [
            Role::Owner->value => $all,
            Role::Admin->value => $adminExceptDelete,
            Role::MarketingManager->value => [
                Permission::OrganizationView,
                Permission::MembersView,
                Permission::TeamsView,
                Permission::TeamsManage,
                Permission::AuditView,
                Permission::FilesView,
                Permission::FilesManage,
                Permission::IntegrationsView,
                Permission::IntegrationsManage,
                ...$crmAll,
                ...$marketingAll,
                ...$seoAll,
                ...$adsAll,
                ...$contentAll,
                ...$salesAll,
                ...$analyticsAll,
                ...$aiAll,
                ...$deliveryAll,
                ...$webAll,
                ...$chatAll,
            ],
            Role::SalesManager->value => [
                Permission::OrganizationView,
                Permission::MembersView,
                Permission::TeamsView,
                Permission::TeamsManage,
                Permission::AuditView,
                Permission::FilesView,
                Permission::FilesManage,
                Permission::IntegrationsView,
                Permission::IntegrationsManage,
                ...$crmAll,
                Permission::MarketingView,
                Permission::MarketingFunnelsView,
                Permission::SeoView,
                Permission::AdsView,
                Permission::ContentView,
                ...$salesAll,
                ...$analyticsAll,
                ...$aiAll,
                ...$deliveryAll,
                Permission::WebView,
                ...$chatAll,
            ],
            Role::MarketingUser->value => [
                Permission::OrganizationView,
                Permission::TeamsView,
                Permission::FilesView,
                Permission::FilesManage,
                Permission::IntegrationsView,
                ...$crmReadWrite,
                ...$marketingBuild,
                ...$seoAll,
                ...$adsAll,
                ...$contentAll,
                Permission::SalesView,
                Permission::AnalyticsView,
                Permission::AnalyticsExperimentsManage,
                Permission::AnalyticsGrowthScoreManage,
                Permission::AiView,
                Permission::AiAgentUse,
                Permission::SupportView,
                Permission::SupportManage,
                Permission::ProjectsView,
                Permission::ProjectsManage,
                Permission::StrategyView,
                Permission::StrategyManage,
                ...$webAll,
                ...$chatWork,
                Permission::ChatWidgetManage,
            ],
            Role::SalesRepresentative->value => [
                Permission::OrganizationView,
                Permission::TeamsView,
                Permission::FilesView,
                Permission::IntegrationsView,
                ...$crmReadWrite,
                Permission::MarketingView,
                Permission::SeoView,
                Permission::AdsView,
                Permission::ContentView,
                Permission::SalesView,
                Permission::SalesBookingManage,
                Permission::AnalyticsView,
                Permission::AiView,
                Permission::AiAgentUse,
                Permission::SupportView,
                Permission::ProjectsView,
                Permission::StrategyView,
                ...$chatWork,
            ],
            Role::Analyst->value => [
                Permission::OrganizationView,
                Permission::AuditView,
                Permission::FilesView,
                Permission::IntegrationsView,
                ...$crmReadOnly,
                Permission::MarketingView,
                Permission::MarketingFunnelsView,
                Permission::SeoView,
                Permission::SeoAuditsManage,
                Permission::AdsView,
                Permission::ContentView,
                Permission::SalesView,
                Permission::AnalyticsView,
                Permission::AnalyticsExperimentsManage,
                Permission::AnalyticsGrowthScoreManage,
                Permission::AiView,
                Permission::AiAgentUse,
                ...$deliveryReadOnly,
                Permission::WebView,
                Permission::ChatView,
            ],
            Role::BillingAdministrator->value => [
                Permission::OrganizationView,
                Permission::BillingView,
                Permission::BillingManage,
            ],
            Role::Viewer->value => [
                Permission::OrganizationView,
                Permission::FilesView,
                Permission::IntegrationsView,
                ...$crmReadOnly,
                Permission::MarketingView,
                Permission::SeoView,
                Permission::AdsView,
                Permission::ContentView,
                Permission::SalesView,
                Permission::AnalyticsView,
                Permission::AiView,
                ...$deliveryReadOnly,
                Permission::WebView,
                Permission::ChatView,
            ],
            // The client portal role is deliberately minimal: portal access and
            // approving its own deliverables. No CRM, marketing, sales,
            // analytics or administrative permissions.
            Role::Client->value => [
                Permission::PortalAccess,
                Permission::ProjectsApprove,
            ],
        ];
    }

    /**
     * The permissions a role grants, as permission-key strings.
     *
     * @return list<string>
     */
    public static function for(string $role): array
    {
        $permissions = self::map()[$role] ?? [];

        return array_map(fn (Permission $p) => $p->value, $permissions);
    }

    public static function grants(string $role, Permission $permission): bool
    {
        return in_array($permission, self::map()[$role] ?? [], true);
    }
}
