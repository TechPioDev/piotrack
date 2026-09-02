<?php

namespace App\Services;

use App\Models\BrandProfile;
use App\Models\Competitor;
use App\Models\File;
use App\Models\KpiTarget;
use App\Models\Organization;
use App\Models\ScoringRule;
use App\Models\SeoAudit;

/**
 * Setup checklist (ONBD-013). Steps are derived from real state, so progress is
 * always accurate and inherently resumable (ONBD-014) — nothing to persist.
 */
class OnboardingChecklist
{
    /**
     * @return array{steps: list<array{key: string, label: string, done: bool, url: string}>, complete: bool}
     */
    public function for(Organization $organization): array
    {
        $subscription = $organization->activeSubscription();
        $onPaidPlan = $subscription !== null && $subscription->status === 'active';
        $memberCount = $organization->members()->wherePivot('status', 'active')->count();
        $hasBillingEmail = $organization->billingProfile()->whereNotNull('billing_email')->exists();
        $hasFile = File::withoutGlobalScope('tenant')->where('organization_id', $organization->id)->exists();

        // The setup wizard's steps derive from the real records it writes
        // (ONBD-006..012) — accurate and resumable like everything else here.
        $orgScoped = fn (string $model) => $model::withoutGlobalScope('tenant')->where('organization_id', $organization->id);

        $steps = [
            ['key' => 'create_org', 'label' => 'Create your organization', 'done' => true, 'url' => '/settings/organization'],
            ['key' => 'choose_plan', 'label' => 'Choose a plan', 'done' => $onPaidPlan, 'url' => '/billing/plans'],
            ['key' => 'invite_team', 'label' => 'Invite your team', 'done' => $memberCount > 1, 'url' => '/settings/members'],
            ['key' => 'billing_details', 'label' => 'Add your billing details', 'done' => $hasBillingEmail, 'url' => '/billing'],
            ['key' => 'upload_asset', 'label' => 'Upload a brand asset', 'done' => $hasFile, 'url' => '/settings/files'],
            ['key' => 'website', 'label' => 'Add your website', 'done' => $orgScoped(BrandProfile::class)->whereNotNull('website_url')->exists(), 'url' => '/onboarding/setup'],
            ['key' => 'goals', 'label' => 'Set your marketing goals', 'done' => $orgScoped(KpiTarget::class)->exists(), 'url' => '/onboarding/setup'],
            ['key' => 'icp', 'label' => 'Describe your ideal customer', 'done' => $orgScoped(ScoringRule::class)->where('name', 'like', 'ICP:%')->exists(), 'url' => '/onboarding/setup'],
            ['key' => 'competitors', 'label' => 'Add your competitors', 'done' => $orgScoped(Competitor::class)->exists(), 'url' => '/onboarding/setup'],
            ['key' => 'first_audit', 'label' => 'Run your first site audit', 'done' => $orgScoped(SeoAudit::class)->exists(), 'url' => '/onboarding/setup'],
        ];

        $complete = collect($steps)->every(fn ($s) => $s['done']);

        return ['steps' => $steps, 'complete' => $complete];
    }
}
