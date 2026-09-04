<?php

namespace App\Services\Content;

use App\Models\AuthorityAsset;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Support\AuditLogger;

/**
 * PR + link-building outreach (DPR + LINK). Prospects move through a pipeline
 * (identified → contacted → replied → won/lost); a won prospect records a
 * placement (media coverage or acquired backlink with anchor + domain authority).
 */
class OutreachService
{
    public function __construct(private AuditLogger $audit) {}

    public function setStatus(OutreachProspect $prospect, string $status): OutreachProspect
    {
        $prospect->update(['status' => $status]);

        return $prospect;
    }

    /** What kind of authority a won placement is (REP-012/014/015/017). */
    public const PLACEMENT_KINDS = ['article', 'press', 'expert_quote', 'backlink', 'podcast_appearance'];

    public function markPlacement(OutreachProspect $prospect, string $url, ?int $domainAuthority = null, ?string $anchorText = null, ?string $linkType = null, string $kind = 'backlink'): OutreachProspect
    {
        $prospect->update([
            'status' => 'won',
            'placement_url' => $url,
            'domain_authority' => $domainAuthority,
            'anchor_text' => $anchorText,
            'link_type' => $linkType,
        ]);

        // The placement is authority earned, not just a prospect row: record it
        // as a typed asset (idempotent on URL) so articles, press, quotes and
        // backlinks live on the reputation surface automatically.
        AuthorityAsset::firstOrCreate(
            ['organization_id' => $prospect->organization_id, 'url' => $url],
            [
                'type' => in_array($kind, self::PLACEMENT_KINDS, true) ? $kind : 'backlink',
                'name' => $prospect->name ?: $prospect->domain ?: $url,
                'issuer' => $prospect->domain,
                'achieved_on' => now()->toDateString(),
                'details' => array_filter([
                    'domain_authority' => $domainAuthority,
                    'anchor_text' => $anchorText,
                    'link_type' => $linkType,
                ], fn ($v) => $v !== null),
            ],
        );

        $this->audit->log('content.outreach.placement', context: ['url' => $url, 'kind' => $kind], resourceType: 'outreach_prospect', resourceId: (string) $prospect->id, organizationId: $prospect->organization_id);

        return $prospect;
    }

    /**
     * @return array{total: int, by_status: array<string, int>, placements: int}
     */
    public function rollup(OutreachCampaign $campaign): array
    {
        $prospects = $campaign->prospects()->get();

        return [
            'total' => $prospects->count(),
            'by_status' => $prospects->groupBy('status')->map->count()->all(),
            'placements' => $prospects->where('status', 'won')->whereNotNull('placement_url')->count(),
        ];
    }
}
