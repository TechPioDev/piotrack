<?php

namespace App\Support;

use RuntimeException;

/**
 * SSRF guard for outbound requests to tenant-supplied URLs (SEC-001).
 *
 * Several features fetch a URL a customer typed — the technical SEO auditor most
 * obviously. Without this, a tenant could point the platform at
 * `http://169.254.169.254/latest/meta-data/` and read cloud credentials out of
 * the response, or probe internal services on the private network, using our
 * server as the attacker. Everything not publicly routable is refused.
 *
 * Resolution happens BEFORE the request: a hostname is resolved and every
 * address it maps to must be public, which also blocks DNS entries that point
 * at private space.
 */
class UrlGuard
{
    /** Only these schemes may ever be fetched. */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * @throws RuntimeException when the URL must not be fetched
     */
    public function assertFetchable(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('Only absolute http(s) URLs can be fetched.');
        }

        if (! in_array(mb_strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true)) {
            // Blocks file://, gopher://, ftp://, dict:// and friends.
            throw new RuntimeException('Only http and https URLs can be fetched.');
        }

        $host = $parts['host'];

        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443, 5050, 8080, 8443], true)) {
            throw new RuntimeException('That port cannot be fetched.');
        }

        foreach ($this->resolve($host) as $ip) {
            if (! $this->isPubliclyRoutable($ip)) {
                throw new RuntimeException('That address is not publicly routable and cannot be fetched.');
            }
        }
    }

    public function isFetchable(string $url): bool
    {
        try {
            $this->assertFetchable($url);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Every address the host resolves to. A literal IP resolves to itself.
     *
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            // Unresolvable hosts are refused rather than handed to the client,
            // which would resolve them again (and possibly differently).
            throw new RuntimeException('That hostname could not be resolved.');
        }

        $ips = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = (string) $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $ips[] = (string) $record['ipv6'];
            }
        }

        return $ips;
    }

    /**
     * Public unicast only: no private, loopback, link-local (which covers the
     * 169.254.169.254 cloud metadata endpoint), or reserved ranges.
     */
    private function isPubliclyRoutable(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
