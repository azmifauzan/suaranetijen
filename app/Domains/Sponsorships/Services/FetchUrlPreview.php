<?php

namespace App\Domains\Sponsorships\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Fetch a plain page <title> from a user-submitted URL for the sponsor entity-matching flow
 * (docs/26): paste a URL first, retrieve it, then match the retrieved title against existing
 * entities — mirrors Pamerin's URL-first progressive disclosure (confirmed live: type a URL,
 * the rest of the form only appears once a preview card renders), adapted to this app's
 * entity-centric, no-auto-create constraint — the fetch only ever produces a title to match
 * against existing entities, never a new listing.
 *
 * SSRF guard: only http/https; the resolved IP must be publicly routable (blocks loopback,
 * RFC1918 private ranges, and link-local — which covers the 169.254.169.254 cloud metadata
 * address); redirects are never followed automatically, so a redirecting URL is reported as
 * unreachable rather than silently chasing an unvalidated Location header.
 *
 * ponytail: resolves only the first A/AAAA record via gethostbyname(), not a DNS-rebinding-proof
 * pinned connection (CURLOPT_RESOLVE). Fine for an MVP preview endpoint that only ever returns a
 * page title, never a source of truth; revisit if this fetcher's output ever gains more trust.
 */
class FetchUrlPreview
{
    private const MAX_BYTES = 200_000;

    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * @return array{title: string, url: string}
     *
     * @throws RuntimeException
     */
    public function handle(string $url): array
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! $host) {
            throw new RuntimeException('URL tidak valid.');
        }

        $ip = $this->resolver->resolve($host);
        if ($ip === $host || ! $this->isPubliclyRoutable($ip)) {
            throw new RuntimeException('URL tidak dapat diakses.');
        }

        try {
            $response = Http::timeout(5)
                ->withOptions(['allow_redirects' => false])
                ->withHeaders(['User-Agent' => 'SuaraNetijenBot/1.0 (+https://suaranetijen.id)'])
                ->get($url);
        } catch (Throwable $e) {
            Log::info('Sponsor URL preview fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            throw new RuntimeException('Gagal mengambil informasi dari URL tersebut.');
        }

        if (! $response->successful()) {
            throw new RuntimeException("URL tidak dapat diakses (status {$response->status()}).");
        }

        $body = substr($response->body(), 0, self::MAX_BYTES);

        if (! preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            throw new RuntimeException('Tidak dapat menemukan judul halaman.');
        }

        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));

        if ($title === '') {
            throw new RuntimeException('Tidak dapat menemukan judul halaman.');
        }

        return ['title' => $title, 'url' => $url];
    }

    private function isPubliclyRoutable(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
