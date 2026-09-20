<?php

namespace App\Domains\Sponsorships\Services;

/**
 * Thin wrapper around gethostbyname() so FetchUrlPreview's SSRF guard is testable without a
 * real DNS lookup — bind a fake in tests instead of hitting the network.
 */
class HostResolver
{
    public function resolve(string $host): string
    {
        return gethostbyname($host);
    }
}
