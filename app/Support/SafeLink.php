<?php

namespace App\Support;

/**
 * A link an owner puts in front of their visitors: a booking page, a map, a
 * Teams meeting.
 *
 * It is shown as a button, never fetched by us - so this is not the SSRF check
 * (that is UrlGuard). It only makes sure the thing is a real web address over
 * https: a `javascript:` address would run in the visitor's browser, and a
 * plain-http one would send them somewhere that can be tampered with on the way.
 */
class SafeLink
{
    public const MAX_LENGTH = 500;

    /** The address, tidied, when it is a usable https link; null otherwise. */
    public static function https(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);
        if ($url === '' || mb_strlen($url) > self::MAX_LENGTH || ! str_starts_with(mb_strtolower($url), 'https://')) {
            return null;
        }

        // No spaces or control characters, and a host to go to.
        if (preg_match('/[\s\x00-\x1F\x7F<>"]/', $url) === 1 || ! is_string(parse_url($url, PHP_URL_HOST))) {
            return null;
        }

        return $url;
    }
}
