<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Derives the acquisition channel of a visit (LEAD-010..014) from first-touch
 * data — UTM medium first (the explicit claim), then source, then the
 * referrer. Pure and deterministic so lead-by-channel reporting is auditable:
 * the same inputs always classify the same way.
 */
class ChannelClassifier
{
    public const CHANNELS = ['organic', 'paid', 'social', 'content', 'referral', 'direct'];

    private const SEARCH_ENGINES = ['google.', 'bing.', 'duckduckgo.', 'yahoo.', 'ecosia.', 'brave.'];

    private const SOCIAL_SITES = ['linkedin.', 'facebook.', 'fb.com', 'twitter.', 'x.com', 't.co', 'instagram.', 'youtube.', 'reddit.', 'tiktok.'];

    public static function classify(?string $utmSource, ?string $utmMedium, ?string $referrer): string
    {
        $medium = Str::lower(trim((string) $utmMedium));
        $source = Str::lower(trim((string) $utmSource));
        $ref = Str::lower(trim((string) $referrer));

        // The explicit medium claim wins.
        if (in_array($medium, ['cpc', 'ppc', 'paid', 'paid_social', 'display', 'retargeting'], true)) {
            return 'paid';
        }
        if (in_array($medium, ['social', 'organic_social'], true) || self::matches($source, self::SOCIAL_SITES)) {
            return 'social';
        }
        if (in_array($medium, ['content', 'blog', 'ebook', 'guide', 'webinar', 'newsletter', 'email'], true)) {
            return 'content';
        }
        if ($medium === 'organic' || self::matches($source, self::SEARCH_ENGINES)) {
            return 'organic';
        }
        if ($medium === 'referral') {
            return 'referral';
        }

        // No UTM claim: fall back to the referrer.
        if ($ref !== '') {
            if (self::matches($ref, self::SEARCH_ENGINES)) {
                return 'organic';
            }
            if (self::matches($ref, self::SOCIAL_SITES)) {
                return 'social';
            }

            return 'referral';
        }

        if ($source !== '') {
            return 'referral';
        }

        return 'direct';
    }

    /**
     * A needle like "facebook." matches both a domain mention
     * ("facebook.com", "m.facebook.com") and the bare source name people type
     * into UTM tags ("facebook").
     *
     * @param  list<string>  $needles
     */
    private static function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle) || $haystack === rtrim($needle, '.')) {
                return true;
            }
        }

        return false;
    }
}
