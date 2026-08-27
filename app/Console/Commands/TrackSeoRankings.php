<?php

namespace App\Console\Commands;

use App\Models\Competitor;
use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Models\Organization;
use App\Services\Seo\RankTracker;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;

/**
 * Daily rank tracking (CINT-001): our position AND every tracked competitor's
 * position on each tracked keyword, so head-to-head is measured, not manual.
 * Our side uses the keyword's mapped page host (a keyword without a mapped URL
 * is skipped — mapping is the honest prerequisite, surfaced on the keywords
 * page as the content gap). Idempotent per keyword+domain per day.
 */
class TrackSeoRankings extends Command
{
    protected $signature = 'seo:track-rankings';

    protected $description = 'Record daily keyword rankings for the tenant and its tracked competitors';

    public function handle(RankTracker $ranks, CurrentOrganization $current): int
    {
        $recorded = 0;

        Organization::query()->each(function (Organization $organization) use ($ranks, $current, &$recorded) {
            $current->set($organization);

            $competitors = Competitor::where('is_tracked', true)->whereNotNull('domain')->get();

            Keyword::where('is_tracked', true)->each(function (Keyword $keyword) use ($ranks, $competitors, &$recorded) {
                $host = $keyword->mapped_url !== null ? parse_url((string) $keyword->mapped_url, PHP_URL_HOST) : null;

                if (is_string($host) && $host !== '' && ! $this->checkedToday($keyword, null)) {
                    $ranks->check($keyword, $host);
                    $recorded++;
                }

                foreach ($competitors as $competitor) {
                    if (! $this->checkedToday($keyword, (string) $competitor->domain)) {
                        $ranks->checkCompetitor($keyword, (string) $competitor->domain);
                        $recorded++;
                    }
                }
            });
        });

        $current->forget();

        $this->components->info("Recorded {$recorded} ranking check(s).");

        return self::SUCCESS;
    }

    private function checkedToday(Keyword $keyword, ?string $competitorDomain): bool
    {
        return KeywordRanking::where('keyword_id', $keyword->id)
            ->where('is_competitor', $competitorDomain !== null)
            ->when($competitorDomain !== null, fn ($q) => $q->where('competitor_domain', $competitorDomain))
            ->whereDate('checked_at', now()->toDateString())
            ->exists();
    }
}
