<?php

namespace App\Domains\Sponsorships\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Fetch a plain page <title> (and, best-effort, its description) from a user-submitted URL for the
 * sponsor entity-matching flow (docs/26): paste a URL first, retrieve it, then match the retrieved
 * title against existing entities — mirrors Pamerin's URL-first progressive disclosure (confirmed
 * live: type a URL, the rest of the form only appears once a preview card renders), adapted to this
 * app's entity-centric, no-auto-create constraint — the fetch only ever produces preview data to
 * match against existing entities, never a new listing.
 *
 * SSRF guard: only http/https; the resolved IP must be publicly routable (blocks loopback,
 * RFC1918 private ranges, and link-local — which covers the 169.254.169.254 cloud metadata
 * address); redirects are followed manually (at most MAX_REDIRECTS hops) and every hop's host is
 * re-validated, so `dana.id` -> `www.dana.id` works without chasing an unvalidated Location header.
 *
 * ponytail: resolves only the first A/AAAA record via gethostbyname(), not a DNS-rebinding-proof
 * pinned connection (CURLOPT_RESOLVE). Fine for an MVP preview endpoint whose output the user edits
 * before it is ever persisted, never a source of truth; revisit if this fetcher's output gains trust.
 */
class FetchUrlPreview
{
    private const MAX_BYTES = 200_000;

    private const MAX_DESCRIPTION_LENGTH = 500;

    private const MAX_REDIRECTS = 3;

    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * @return array{title: string, url: string, description: string|null}
     *
     * @throws RuntimeException
     */
    public function handle(string $url): array
    {
        $redirects = 0;

        while (true) {
            $origin = $this->assertPublicHttpUrl($url);

            try {
                $response = Http::timeout(5)
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders(['User-Agent' => 'SuaraNetijenBot/1.0 (+https://suaranetijen.id)'])
                    ->get($url);
            } catch (Throwable $e) {
                Log::info('Sponsor URL preview fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

                throw new RuntimeException('Gagal mengambil informasi dari URL tersebut.');
            }

            $location = $response->redirect() ? $response->header('Location') : '';

            if ($location === '') {
                break;
            }

            if (++$redirects > self::MAX_REDIRECTS) {
                throw new RuntimeException('URL tidak dapat diakses (terlalu banyak pengalihan).');
            }

            $url = $this->absoluteUrl($location, $origin);
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

        return [
            'title' => $title,
            'url' => $url,
            'description' => $this->extractDescription($body),
        ];
    }

    /**
     * Best-effort page description to pre-fill the sponsor's description input: prefers
     * og:description (what RankUp/getRanked auto-fill from), falls back to the plain meta
     * description. Never fatal — a page with neither returns null and the input simply starts empty.
     */
    private function extractDescription(string $body): ?string
    {
        if (! preg_match_all('/<meta\b[^>]*>/i', $body, $tags)) {
            return null;
        }

        foreach (['og:description', 'description'] as $key) {
            foreach ($tags[0] as $tag) {
                if (! preg_match('/\b(?:property|name)\s*=\s*(["\'])(.*?)\1/is', $tag, $keyMatch)) {
                    continue;
                }

                if (strcasecmp(trim($keyMatch[2]), $key) !== 0) {
                    continue;
                }

                if (! preg_match('/\bcontent\s*=\s*(["\'])(.*?)\1/is', $tag, $contentMatch)) {
                    continue;
                }

                $description = trim(html_entity_decode(strip_tags($contentMatch[2]), ENT_QUOTES | ENT_HTML5));

                if ($description !== '') {
                    return mb_substr($description, 0, self::MAX_DESCRIPTION_LENGTH);
                }
            }
        }

        return null;
    }

    /**
     * @return array{scheme: string, host: string, port: int|null}
     *
     * @throws RuntimeException
     */
    public function assertPublicHttpUrl(string $url): array
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (! in_array($scheme, ['http', 'https'], true) || ! $host) {
            throw new RuntimeException('URL tidak valid.');
        }

        $ip = $this->resolver->resolve($host);
        if ($ip === $host || ! $this->isPubliclyRoutable($ip)) {
            throw new RuntimeException('URL tidak dapat diakses.');
        }

        return ['scheme' => $scheme, 'host' => $host, 'port' => $parts['port'] ?? null];
    }

    /**
     * @param  array{scheme: string, host: string, port: int|null}  $origin
     */
    private function absoluteUrl(string $location, array $origin): string
    {
        if (preg_match('~^https?://~i', $location)) {
            return $location;
        }

        if (str_starts_with($location, '//')) {
            return $origin['scheme'].':'.$location;
        }

        $port = $origin['port'] !== null ? ':'.$origin['port'] : '';

        return $origin['scheme'].'://'.$origin['host'].$port.'/'.ltrim($location, '/');
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
