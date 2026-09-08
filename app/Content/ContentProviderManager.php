<?php

namespace App\Content;

use App\Content\Contracts\ReviewProvider;
use App\Content\Contracts\SocialListeningProvider;
use App\Content\Contracts\SocialProvider;
use App\Content\Providers\FixtureReviewProvider;
use App\Content\Providers\FixtureSocialListeningProvider;
use App\Content\Providers\FixtureSocialProvider;
use App\Content\Providers\LiveReviewProvider;
use App\Content\Providers\LiveSocialProvider;

/**
 * Resolves the active social / review drivers from config (ADR-0007). Default
 * `fixture` is tested; `live` uses the real vendor drivers (credentials).
 */
class ContentProviderManager
{
    public function social(?string $name = null): SocialProvider
    {
        $name ??= (string) config('content.social_provider', 'fixture');

        return $name === 'fixture' ? new FixtureSocialProvider : new LiveSocialProvider;
    }

    /** SOC-022/023/024: the social-listening driver (fixture default). */
    public function listening(?string $name = null): SocialListeningProvider
    {
        $name ??= (string) config('content.listening_provider', 'fixture');

        return match ($name) {
            'fixture' => new FixtureSocialListeningProvider,
            default => throw new \InvalidArgumentException("Unknown listening provider [{$name}]."),
        };
    }

    public function review(?string $name = null): ReviewProvider
    {
        $name ??= (string) config('content.review_provider', 'fixture');

        return $name === 'fixture' ? new FixtureReviewProvider : new LiveReviewProvider;
    }
}
