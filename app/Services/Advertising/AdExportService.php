<?php

namespace App\Services\Advertising;

use App\Models\AdCampaign;
use App\Models\AdExtension;
use Illuminate\Validation\ValidationException;

/**
 * PPC-001/002: bulk-editor CSV export. The platform is the account of record
 * for structure; this export produces rows a tenant can upload today with
 * Google Ads Editor or the Microsoft Ads bulk tool — the no-API half of the
 * delivery story. Live API sync stays behind the AdProvider seam (ADR-0006).
 */
class AdExportService
{
    public const FORMATS = ['google', 'microsoft'];

    private const MATCH_TYPES = ['exact' => 'Exact', 'phrase' => 'Phrase', 'broad' => 'Broad'];

    /**
     * One flat editor sheet: campaign, ad group, keyword, ad and extension
     * rows share the column set, each row filling the columns its level uses.
     *
     * @return array{filename: string, rows: list<list<string>>}
     */
    public function editorCsv(AdCampaign $campaign, string $format): array
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages(['format' => __('Unknown export format.')]);
        }

        $campaign->load(['groups.ads', 'groups.keywords', 'extensions']);

        $budget = number_format($campaign->daily_budget / 100, 2, '.', '');
        $rows = [[
            'Campaign', 'Ad Group', 'Row Type', 'Campaign Daily Budget', 'Max CPC',
            'Keyword', 'Match Type', 'Headline', 'Description', 'Final URL', 'Phone Number',
        ]];

        $rows[] = [$campaign->name, '', 'Campaign', $budget, '', '', '', '', '', '', ''];

        foreach ($campaign->groups as $group) {
            $maxCpc = $group->bid_amount !== null ? number_format(((int) $group->bid_amount) / 100, 2, '.', '') : '';
            $rows[] = [$campaign->name, $group->name, 'Ad Group', '', $maxCpc, '', '', '', '', '', ''];

            foreach ($group->keywords as $keyword) {
                $rows[] = [
                    $campaign->name, $group->name,
                    $keyword->is_negative ? 'Negative Keyword' : 'Keyword',
                    '', '',
                    $keyword->phrase,
                    self::MATCH_TYPES[$keyword->match_type] ?? 'Broad',
                    '', '', '', '',
                ];
            }

            foreach ($group->ads as $ad) {
                $rows[] = [
                    $campaign->name, $group->name, 'Ad', '', '', '', '',
                    (string) $ad->headline, (string) $ad->body, (string) $ad->destination_url, '',
                ];
            }
        }

        foreach ($campaign->extensions as $extension) {
            /** @var AdExtension $extension */
            $rows[] = [
                $campaign->name, '', match ($extension->kind) {
                    'sitelink' => 'Sitelink Extension',
                    'callout' => 'Callout Extension',
                    'structured_snippet' => 'Structured Snippet Extension',
                    default => 'Call Extension',
                },
                '', '', '', '', $extension->text, '', (string) $extension->url, (string) $extension->phone,
            ];
        }

        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $campaign->name));

        return [
            'filename' => sprintf('%s-%s-editor.csv', $format, trim($slug, '-')),
            'rows' => $rows,
        ];
    }
}
