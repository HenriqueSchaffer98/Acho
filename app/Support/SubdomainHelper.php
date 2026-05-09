<?php

declare(strict_types=1);

namespace App\Support;

class SubdomainHelper
{
    /**
     * Extract and normalise the tenant slug from a Host header value.
     *
     * Returns null when the host is the root domain, www, or does not match
     * the expected pattern (ADR-016).
     */
    public static function extractSlug(string $host, string $baseDomain): ?string
    {
        // Strip port if present (e.g. "tenant.acho.test:80")
        $host = strtolower(explode(':', $host)[0]);
        $baseDomain = strtolower($baseDomain);

        // Root domain or www — not a tenant subdomain.
        if ($host === $baseDomain || $host === 'www.' . $baseDomain) {
            return null;
        }

        // Must end with ".<baseDomain>"
        $suffix = '.' . $baseDomain;
        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));

        // Slug must be a single label (no dots) and match [a-z0-9-] only,
        // max 30 chars (convention 04-database).
        if (! preg_match('/^[a-z0-9][a-z0-9\-]{0,28}[a-z0-9]$|^[a-z0-9]$/', $slug)) {
            return null;
        }

        return $slug;
    }
}
