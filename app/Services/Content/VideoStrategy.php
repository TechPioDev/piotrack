<?php

namespace App\Services\Content;

use App\Models\ContentPiece;

/**
 * VID-001: the video strategy report, computed from the tenant's OWN video
 * records — cadence, type mix, funnel coverage, published share — with
 * recommendations that cite the number that triggered them. Below the data
 * floor it says there is not enough history instead of guessing. Strategy
 * authoring stays human; this report informs it.
 */
class VideoStrategy
{
    /** Video-shaped content types. */
    public const VIDEO_TYPES = ['video', 'webinar', 'podcast', 'interview'];

    /** Minimum video pieces before recommendations are computed. */
    public const MIN_PIECES = 3;

    /**
     * @return array{pieces: int, published: int, per_month: float, mix: array<string, int>, funnel: array<string, int>, sufficient: bool, recommendations: list<string>}
     */
    public function report(): array
    {
        $videos = ContentPiece::whereIn('content_type', self::VIDEO_TYPES)->get();

        $mix = $videos->countBy('content_type')->all();
        $funnel = ['tof' => 0, 'mof' => 0, 'bof' => 0];
        foreach ($videos as $piece) {
            if (isset($funnel[(string) $piece->funnel_stage])) {
                $funnel[(string) $piece->funnel_stage]++;
            }
        }

        $recent = $videos->filter(fn (ContentPiece $p) => $p->created_at !== null && $p->created_at->gte(now()->subDays(90)))->count();
        $published = $videos->where('status', 'published')->count();

        $report = [
            'pieces' => $videos->count(),
            'published' => $published,
            'per_month' => round($recent / 3, 1),
            'mix' => $mix,
            'funnel' => $funnel,
            'sufficient' => $videos->count() >= self::MIN_PIECES,
            'recommendations' => [],
        ];

        if (! $report['sufficient']) {
            return $report;
        }

        $recs = [];
        if ($recent === 0) {
            $recs[] = __('No video published in 90 days against a library of :n — a monthly cadence keeps the channel alive.', ['n' => $videos->count()]);
        } elseif ($report['per_month'] < 1) {
            $recs[] = __('Cadence is :n videos/month over the last 90 days — below one per month, rankings and subscriber growth stall.', ['n' => $report['per_month']]);
        }

        if ($funnel['bof'] === 0) {
            $recs[] = __('No bottom-funnel video (:tof TOF, :mof MOF, 0 BOF) — a testimonial or case-study video closes what education opens.', ['tof' => $funnel['tof'], 'mof' => $funnel['mof']]);
        }

        if ($videos->count() > 0 && $published < (int) ceil($videos->count() / 2)) {
            $recs[] = __('Only :published of :total video pieces are published — ship the drafts before recording more.', ['published' => $published, 'total' => $videos->count()]);
        }

        $report['recommendations'] = $recs;

        return $report;
    }
}
