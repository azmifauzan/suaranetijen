<?php

namespace App\Domains\Entities\CandidateSources;

use App\Domains\Entities\Contracts\EntityCandidateSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

/**
 * Carisinyal's dedicated phone/tablet spec-page sitemap
 * (carisinyal.com/hp-sitemap{1..4}.xml — a WordPress custom post type,
 * confirmed live 6 Sep 2026 at ~3,164 URLs). Each URL slug names one
 * specific device (e.g. "vivo-y500-2", "itel-s26-ultra"), giving direct
 * coverage of new Smartphone-category models that neither Wikidata's
 * broad SPARQL query nor the trend/news feeds reliably surface — those
 * only catch a device once it's already a headline, not the day a spec
 * page goes up. Weighted by recency so the newest devices rank highest in
 * the admin review queue; only the most recently updated slice is taken
 * each run rather than all ~3,164 (already-seen ones would just be
 * deduped by EntityCandidateAggregator anyway, but there's no value in
 * repeatedly re-fetching sitemap entries from months ago).
 */
class CarisinyalCandidateSource implements EntityCandidateSource
{
    private const SITEMAP_URLS = [
        'https://carisinyal.com/hp-sitemap1.xml',
        'https://carisinyal.com/hp-sitemap2.xml',
        'https://carisinyal.com/hp-sitemap3.xml',
        'https://carisinyal.com/hp-sitemap4.xml',
    ];

    private const MAX_CANDIDATES = 200;

    public function sourceType(): string
    {
        return 'carisinyal';
    }

    /**
     * @return list<array{raw_term: string, weight: int}>
     */
    public function discover(): array
    {
        $entries = [];

        foreach (self::SITEMAP_URLS as $url) {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'SuaraNetijen/1.0 (+https://suaranetijen.id/sources)'])
                ->get($url);

            if (! $response->successful()) {
                continue;
            }

            $xml = @simplexml_load_string($response->body());
            if (! $xml instanceof SimpleXMLElement) {
                continue;
            }

            foreach ($xml->url as $urlNode) {
                $loc = trim((string) $urlNode->loc);
                $slug = $this->deviceSlug($loc);
                if ($slug === null) {
                    continue;
                }

                $lastmod = trim((string) $urlNode->lastmod);
                $entries[] = [
                    'name' => $this->slugToName($slug),
                    'lastmod' => $lastmod !== '' ? CarbonImmutable::parse($lastmod) : CarbonImmutable::now()->subYears(10),
                ];
            }
        }

        usort($entries, fn (array $a, array $b): int => $b['lastmod']->timestamp <=> $a['lastmod']->timestamp);
        $entries = array_slice($entries, 0, self::MAX_CANDIDATES);

        $now = CarbonImmutable::now();

        return array_map(fn (array $entry): array => [
            'raw_term' => $entry['name'],
            'weight' => max(1, 90 - (int) $entry['lastmod']->diffInDays($now)),
        ], $entries);
    }

    /**
     * Only "/hp/{slug}/" device pages — excludes the "/hp/" listing index
     * itself, which is also in the sitemap with no slug segment.
     */
    private function deviceSlug(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if (! str_starts_with($path, 'hp/')) {
            return null;
        }

        $slug = substr($path, strlen('hp/'));

        return $slug !== '' ? $slug : null;
    }

    private function slugToName(string $slug): string
    {
        return str_replace('-', ' ', ucwords($slug, '-'));
    }
}
