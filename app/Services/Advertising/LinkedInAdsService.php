<?php

namespace App\Services\Advertising;

use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\AdGroup;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\RetargetingAudience;
use App\Services\Sales\AccountService;
use App\Support\AuditLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * LinkedIn Advertising workflows that need no Marketing API (LIAD-002/013/
 * 014/015/016/017): content and case-study promotion into draft campaigns,
 * matched-audience attachment, ABM tier campaigns, the lead-gen form CSV
 * import (Campaign Manager's own export), and the Campaign Manager brief.
 * Live delivery, audience push and form sync stay behind ADR-0006.
 */
class LinkedInAdsService
{
    /** LinkedIn sponsored-content creative limits (enforced server-side). */
    public const HEADLINE_LIMIT = 70;

    public const INTRO_LIMIT = 150;

    /** Campaign Manager lead export headers → contact fields. */
    private const LEAD_ALIASES = [
        'first_name' => ['first name', 'firstname'],
        'last_name' => ['last name', 'lastname'],
        'email' => ['email address', 'email', 'work email'],
        'title' => ['job title', 'title'],
        'campaign' => ['campaign name', 'campaign'],
        'form' => ['form name', 'lead gen form name', 'form'],
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * LIAD-016/017 (and the LIAD-002 workflow): a content piece becomes a
     * draft LinkedIn sponsored-content campaign with creative from the piece
     * itself. Case studies default to a conversions objective — they are
     * bottom-funnel proof; everything else promotes for awareness.
     */
    public function promoteContent(ContentPiece $piece): AdCampaign
    {
        $existing = AdCampaign::where('platform', 'linkedin')
            ->where('targeting->content_piece_id', $piece->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $isCaseStudy = $piece->content_type === 'case_study';

        $campaign = AdCampaign::create([
            'platform' => 'linkedin',
            'name' => ($isCaseStudy ? 'Case study: ' : 'Promote: ').mb_substr($piece->title, 0, 120),
            'type' => 'sponsored_content',
            'objective' => $isCaseStudy ? 'conversions' : 'awareness',
            'status' => 'draft',
            'daily_budget' => 0,
            'targeting' => ['content_piece_id' => $piece->id, 'content_type' => $piece->content_type],
        ]);

        $group = AdGroup::create([
            'ad_campaign_id' => $campaign->id,
            'name' => 'Sponsored content',
            'status' => 'draft',
        ]);

        Ad::create([
            'ad_group_id' => $group->id,
            'name' => mb_substr($piece->title, 0, 100),
            'headline' => Str::limit($piece->title, self::HEADLINE_LIMIT, ''),
            'body' => Str::limit((string) ($piece->excerpt ?: $piece->title), self::INTRO_LIMIT, ''),
            'destination_url' => $piece->url,
            'status' => 'draft',
        ]);

        $this->audit->log('ads.linkedin.content_promoted', context: ['piece' => $piece->id, 'campaign' => $campaign->id], resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return $campaign;
    }

    /**
     * LIAD-013: point a LinkedIn campaign at a retargeting audience. The
     * audience's matched-audience CSV export is the upload file; the brief
     * names it next to the targeting facets.
     */
    public function attachAudience(AdCampaign $campaign, RetargetingAudience $audience): AdCampaign
    {
        if ($campaign->platform !== 'linkedin') {
            throw ValidationException::withMessages(['audience_id' => __('Matched audiences attach to LinkedIn campaigns only.')]);
        }

        $campaign->update(['targeting' => array_merge($campaign->targeting ?? [], [
            'audience_id' => $audience->id,
            'audience_name' => $audience->name,
        ])]);

        $this->audit->log('ads.linkedin.audience_attached', context: ['audience' => $audience->id], resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return $campaign;
    }

    /**
     * LIAD-014: an ABM tier becomes a draft LinkedIn campaign targeting the
     * tier's committee audience (built/refreshed through the P26 machinery).
     * Idempotent per tier.
     */
    public function abmCampaign(int $tier, AccountService $accounts, RetargetingService $retargeting): AdCampaign
    {
        $audience = $accounts->createRetargetingAudience($tier, $retargeting);

        $existing = AdCampaign::where('platform', 'linkedin')
            ->where('targeting->abm_tier', $tier)->first();

        if ($existing !== null) {
            return $this->attachAudience($existing, $audience);
        }

        $campaign = AdCampaign::create([
            'platform' => 'linkedin',
            'name' => "ABM Tier {$tier} — target accounts",
            'type' => 'abm',
            'objective' => 'leads',
            'status' => 'draft',
            'daily_budget' => 0,
            'targeting' => ['abm_tier' => $tier],
        ]);

        return $this->attachAudience($campaign, $audience);
    }

    /**
     * LIAD-015: import Campaign Manager's lead-gen form export CSV. Contacts
     * are matched by email; lead_source is set once and never overwritten —
     * a lead that came from somewhere else first keeps its origin.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importLeads(string $path, ?string $fallbackCampaign = null): array
    {
        $counts = app(AdLeadImporter::class)->import($path, self::LEAD_ALIASES, 'linkedin', $fallbackCampaign);

        $this->audit->log('ads.linkedin.leads_imported', context: $counts);

        return $counts;
    }

    /**
     * LIAD-002: the Campaign Manager brief — settings, targeting facets,
     * linked audience (naming its upload file), and creatives. LinkedIn has
     * no bulk-editor import, so the brief is the honest no-API handoff.
     *
     * @return array{filename: string, rows: list<list<string>>}
     */
    public function brief(AdCampaign $campaign): array
    {
        if ($campaign->platform !== 'linkedin') {
            throw ValidationException::withMessages(['campaign' => __('The Campaign Manager brief is for LinkedIn campaigns.')]);
        }

        $campaign->load(['groups.ads']);

        $rows = [['Section', 'Setting', 'Value']];
        $rows[] = ['Campaign', 'Name', $campaign->name];
        $rows[] = ['Campaign', 'Objective', (string) $campaign->objective];
        $rows[] = ['Campaign', 'Daily budget', number_format($campaign->daily_budget / 100, 2, '.', '')];
        $rows[] = ['Campaign', 'Status', 'Create as PAUSED, review, then launch'];

        foreach ($campaign->targeting ?? [] as $facet => $value) {
            if (in_array($facet, ['audience_id', 'audience_name', 'content_piece_id'], true)) {
                continue;
            }
            $rows[] = ['Targeting', (string) $facet, is_array($value) ? implode('; ', array_map('strval', $value)) : (string) $value];
        }

        $audienceId = $campaign->targeting['audience_id'] ?? null;
        if ($audienceId !== null) {
            $audience = RetargetingAudience::find($audienceId);
            if ($audience !== null) {
                $rows[] = ['Matched audience', $audience->name, __('Upload the audience export CSV under Plan > Audiences > Upload a list (:n members).', ['n' => $audience->member_count])];
            }
        }

        foreach ($campaign->groups as $group) {
            foreach ($group->ads as $ad) {
                $rows[] = ['Creative', (string) $ad->headline, (string) $ad->body];
                if ($ad->destination_url !== null) {
                    $rows[] = ['Creative', 'Destination URL', (string) $ad->destination_url];
                }
            }
        }

        $slug = Str::slug($campaign->name);

        return ['filename' => "linkedin-{$slug}-brief.csv", 'rows' => $rows];
    }
}
