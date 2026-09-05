<?php

namespace App\Services\Advertising;

use App\Models\Ad;
use App\Models\AdGroup;
use App\Services\Ai\AiGateway;
use Illuminate\Support\Str;

/**
 * PPC-016: AI-drafted search-ad copy through the governed gateway. The result
 * is always a DRAFT ad on the group — never activated, never pushed to a
 * platform — with the search platforms' character limits enforced (30-char
 * headlines, 90-char descriptions) regardless of what the model returns.
 */
class AdCopywriter
{
    public const HEADLINE_LIMIT = 30;

    public const DESCRIPTION_LIMIT = 90;

    public function __construct(private AiGateway $gateway) {}

    /**
     * Draft copy for the group from its campaign's own targeting: service line,
     * objective, keywords and destination. Returns the created draft ad plus
     * every parsed variant for the rep to choose from.
     *
     * @return array{ad: Ad, headlines: list<string>, descriptions: list<string>}
     */
    public function draft(AdGroup $group): array
    {
        $campaign = $group->campaign()->firstOrFail();
        $keywords = $group->keywords()->where('is_negative', false)->pluck('phrase');
        $destination = (string) ($group->ads()->whereNotNull('destination_url')->value('destination_url') ?? '');

        $text = $this->gateway->run('ads.copy', 'ads.copy', [
            'service' => (string) ($campaign->serviceLine()->value('name') ?? $campaign->name),
            'objective' => (string) $campaign->objective,
            'keywords' => $keywords->isEmpty() ? 'none recorded' : $keywords->implode(', '),
            'destination' => $destination === '' ? 'not set' : $destination,
        ])->text;

        $headlines = $this->parse($text, 'HEADLINE', self::HEADLINE_LIMIT);
        $descriptions = $this->parse($text, 'DESCRIPTION', self::DESCRIPTION_LIMIT);

        $ad = Ad::create([
            'ad_group_id' => $group->id,
            'name' => 'AI draft — '.now()->format('M j H:i'),
            'headline' => $headlines[0] ?? Str::limit($campaign->name, self::HEADLINE_LIMIT, ''),
            'body' => $descriptions[0] ?? '',
            'destination_url' => $destination !== '' ? $destination : null,
            'status' => 'draft',
        ]);

        return ['ad' => $ad, 'headlines' => $headlines, 'descriptions' => $descriptions];
    }

    /**
     * Pull `LABEL: value` lines out of the model response, enforcing the limit.
     *
     * @return list<string>
     */
    private function parse(string $text, string $label, int $limit): array
    {
        preg_match_all('/^'.$label.':\s*(.+)$/mi', $text, $matches);

        $out = [];
        foreach ($matches[1] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = Str::limit($line, $limit, '');
            }
        }

        return $out;
    }
}
