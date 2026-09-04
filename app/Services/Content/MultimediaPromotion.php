<?php

namespace App\Services\Content;

use App\Models\ContentPiece;
use App\Models\SocialPost;
use App\Support\AuditLogger;
use RuntimeException;

/**
 * Webinar promotion + social clips (POD-004/009): multimedia pieces are
 * distributed through the tested social pipeline — one scheduled announcement
 * per network, and a staggered clip schedule the tenant attaches their cuts
 * to. The platform schedules and distributes; cutting the video itself is
 * production work, and nothing here pretends otherwise.
 */
class MultimediaPromotion
{
    /** The content types this applies to — everything else is refused. */
    public const MULTIMEDIA_TYPES = ['webinar', 'video', 'podcast', 'interview'];

    private const NETWORKS = ['linkedin', 'facebook', 'x', 'youtube'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * One scheduled announcement post per network, staggered hourly so the
     * networks do not fire in the same minute. Copy is scaffolded from the
     * piece's own title/excerpt/url — real data, never invented claims.
     *
     * @return list<SocialPost>
     */
    public function promote(ContentPiece $piece): array
    {
        $this->assertMultimedia($piece);

        $link = $piece->url !== null && $piece->url !== '' ? "\n\n".$piece->url : '';
        $noun = $piece->content_type === 'webinar' ? 'webinar' : ($piece->content_type === 'podcast' ? 'episode' : 'video');
        $register = $piece->content_type === 'webinar' ? ' Save your seat:' : ' Watch:';

        $posts = [];
        foreach (self::NETWORKS as $i => $network) {
            $posts[] = SocialPost::create([
                'channel' => $network,
                'type' => 'promo',
                'content_piece_id' => $piece->id,
                'body' => ucfirst($noun).': '.$piece->title.($piece->excerpt !== null && $piece->excerpt !== '' ? ' — '.$piece->excerpt : '').$register.$link,
                'status' => 'scheduled',
                'scheduled_at' => now()->addHours($i + 1),
            ]);
        }

        $this->audit->log('content.multimedia.promoted', context: ['piece' => $piece->title, 'posts' => count($posts)], resourceType: 'content_piece', resourceId: (string) $piece->id, organizationId: $piece->organization_id);

        return $posts;
    }

    /**
     * A staggered clip schedule (one per day, rotating networks): each post is
     * typed `clip`, linked to the source piece, with guidance copy and an
     * empty media slot for the tenant's actual cut.
     *
     * @return list<SocialPost>
     */
    public function clips(ContentPiece $piece, int $count = 3): array
    {
        $this->assertMultimedia($piece);
        $count = max(1, min(10, $count));

        $posts = [];
        foreach (range(1, $count) as $i) {
            $network = self::NETWORKS[($i - 1) % count(self::NETWORKS)];
            $posts[] = SocialPost::create([
                'channel' => $network,
                'type' => 'clip',
                'content_piece_id' => $piece->id,
                'body' => "Clip {$i} of {$count} from \"{$piece->title}\" — pull one self-contained moment (a question answered, a stat, a strong claim), keep it under 90 seconds, and attach the cut before this goes out.",
                'status' => 'scheduled',
                'scheduled_at' => now()->addDays($i),
            ]);
        }

        $this->audit->log('content.multimedia.clips', context: ['piece' => $piece->title, 'clips' => $count], resourceType: 'content_piece', resourceId: (string) $piece->id, organizationId: $piece->organization_id);

        return $posts;
    }

    private function assertMultimedia(ContentPiece $piece): void
    {
        if (! in_array($piece->content_type, self::MULTIMEDIA_TYPES, true)) {
            throw new RuntimeException('Promotion schedules are for multimedia pieces (webinar, video, podcast, interview).');
        }
    }
}
