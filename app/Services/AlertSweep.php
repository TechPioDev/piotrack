<?php

namespace App\Services;

use App\Billing\UsageMeter;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AiVisibilityChangeNotification;
use App\Notifications\CompetitorOutrankNotification;
use App\Notifications\PlatformNotification;
use App\Notifications\RankingDropNotification;
use App\Notifications\UsageLimitApproachingNotification;
use App\Services\Ai\AiVisibilityDashboard;
use App\Support\NotificationDispatcher;

/**
 * The daily alert sweep (ALRT module): usage limits approaching, tracked
 * keywords falling, AI-visibility swings. Runs inside one organization's
 * tenant context (the command sets it per org). Every alert is deduped by its
 * key per recipient per day, so re-running the sweep never double-notifies.
 */
class AlertSweep
{
    /** Warn when a metered limit reaches this share. */
    public const USAGE_THRESHOLD = 0.8;

    /** Positions a keyword must fall between checks to alert. */
    public const RANKING_DROP = 5;

    public function __construct(
        private UsageMeter $usage,
        private AiVisibilityDashboard $aiVisibility,
        private NotificationDispatcher $notifier,
    ) {}

    /**
     * @return array{usage: int, rankings: int, ai_visibility: int}
     */
    public function run(Organization $organization): array
    {
        return [
            'usage' => $this->checkUsage($organization),
            'rankings' => $this->checkRankingDrops($organization),
            'ai_visibility' => $this->checkAiVisibility($organization),
            'competitors' => $this->checkCompetitorOutranks($organization),
        ];
    }

    /**
     * A tracked competitor's latest recorded position beats ours on a tracked
     * keyword (CINT-013). Deduped per keyword+competitor per day.
     */
    private function checkCompetitorOutranks(Organization $organization): int
    {
        $sent = 0;

        Keyword::where('is_tracked', true)->whereNotNull('current_position')->each(function (Keyword $keyword) use ($organization, &$sent) {
            $latestPerCompetitor = $keyword->rankings()
                ->where('is_competitor', true)->whereNotNull('competitor_domain')
                ->orderByDesc('checked_at')->get(['competitor_domain', 'position'])
                ->unique('competitor_domain');

            foreach ($latestPerCompetitor as $ranking) {
                if ($ranking->position !== null && (int) $ranking->position < (int) $keyword->current_position) {
                    $sent += $this->notifyOwners($organization, new CompetitorOutrankNotification(
                        $keyword->id,
                        $keyword->phrase,
                        (string) $ranking->competitor_domain,
                        (int) $ranking->position,
                        (int) $keyword->current_position,
                    ));
                }
            }
        });

        return $sent;
    }

    private function checkUsage(Organization $organization): int
    {
        $sent = 0;

        foreach ($this->usage->summary($organization) as $row) {
            if ($row['limit'] === null || $row['limit'] === 0) {
                continue;
            }
            if ($row['used'] / $row['limit'] >= self::USAGE_THRESHOLD) {
                $sent += $this->notifyOwners($organization, new UsageLimitApproachingNotification($row['key'], $row['used'], $row['limit']));
            }
        }

        return $sent;
    }

    private function checkRankingDrops(Organization $organization): int
    {
        $sent = 0;

        Keyword::where('is_tracked', true)->each(function (Keyword $keyword) use ($organization, &$sent) {
            $last = $keyword->rankings()->where('is_competitor', false)
                ->orderByDesc('checked_at')->limit(2)->get(['position', 'checked_at']);

            if ($last->count() < 2 || $last[0]->position === null || $last[1]->position === null) {
                return;
            }

            // Higher position number = worse rank.
            if ($last[0]->position - $last[1]->position >= self::RANKING_DROP) {
                $sent += $this->notifyOwners(
                    $organization,
                    new RankingDropNotification($keyword->id, $keyword->phrase, (int) $last[1]->position, (int) $last[0]->position),
                );
            }
        });

        return $sent;
    }

    private function checkAiVisibility(Organization $organization): int
    {
        $alert = $this->aiVisibility->alert();

        if (! $alert['changed']) {
            return 0;
        }

        return $this->notifyOwners(
            $organization,
            new AiVisibilityChangeNotification($alert['direction'], $alert['delta'], $alert['current']),
        );
    }

    /** Send to each owner unless the same key already reached them today. */
    private function notifyOwners(Organization $organization, PlatformNotification $notification): int
    {
        $sent = 0;

        foreach ($organization->owners()->get() as $owner) {
            if ($this->alreadySentToday($owner, (string) $notification->dedupeKey())) {
                continue;
            }
            $this->notifier->toUser($owner, $notification);
            $sent++;
        }

        return $sent;
    }

    private function alreadySentToday(User $user, string $key): bool
    {
        return $user->notifications()
            ->where('created_at', '>=', now()->startOfDay())
            ->where('data->key', $key)
            ->exists();
    }
}
