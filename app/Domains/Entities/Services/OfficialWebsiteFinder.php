<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Entity;
use Illuminate\Support\Facades\Http;

class OfficialWebsiteFinder
{
    private const ENDPOINT = 'https://www.wikidata.org/w/api.php';

    private const USER_AGENT = 'SuaraNetijen/1.0 (+https://suaranetijen.id/sources)';

    /**
     * Find official website for an entity using Wikidata.
     */
    public function find(Entity $entity): ?string
    {
        $queries = $this->buildSearchQueries($entity);

        foreach ($queries as $query) {
            $url = $this->searchAndExtractWebsite($entity, $query);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function buildSearchQueries(Entity $entity): array
    {
        $queries = [];
        $trimmedName = trim($entity->name);
        if ($trimmedName !== '') {
            $queries[] = $trimmedName;
        }

        $parent = $entity->parent ?? ($entity->parent_id !== null ? $entity->parent()->first() : null);
        if ($parent !== null) {
            $parentName = trim($parent->name);
            if ($parentName !== '' && ! str_contains(mb_strtolower($trimmedName), mb_strtolower($parentName))) {
                $queries[] = "{$parentName} {$trimmedName}";
            }
        }

        if ($entity->relationLoaded('aliases')) {
            $firstAlias = $entity->aliases->first()?->alias;
            if (is_string($firstAlias) && trim($firstAlias) !== '') {
                $queries[] = trim($firstAlias);
            }
        }

        return array_values(array_unique($queries));
    }

    private function searchAndExtractWebsite(Entity $entity, string $query): ?string
    {
        $candidates = $this->searchCandidates($query, 'id');
        if (empty($candidates)) {
            $candidates = $this->searchCandidates($query, 'en');
        }

        if (empty($candidates)) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ($this->isIrrelevantCandidate($entity, $candidate)) {
                continue;
            }

            $candidateId = (string) ($candidate['id'] ?? '');
            if ($candidateId === '' || preg_match('/^Q\d+$/', $candidateId) !== 1) {
                continue;
            }

            $website = $this->fetchCandidateWebsite($candidateId);
            if ($website !== null) {
                return $website;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchCandidates(string $query, string $language): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::ENDPOINT, [
                    'action' => 'wbsearchentities',
                    'search' => $query,
                    'language' => $language,
                    'uselang' => $language,
                    'format' => 'json',
                    'limit' => 5,
                    'type' => 'item',
                ]);

            if (! $response->successful()) {
                return [];
            }

            $rawSearch = (array) $response->json('search', []);
            $candidates = [];
            foreach ($rawSearch as $item) {
                if (is_array($item)) {
                    $candidates[] = $item;
                }
            }

            return $candidates;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function isIrrelevantCandidate(Entity $entity, array $candidate): bool
    {
        $description = mb_strtolower((string) ($candidate['description'] ?? ''));

        if (in_array($entity->type, [EntityType::Brand, EntityType::Product, EntityType::Service], true)) {
            $irrelevantKeywords = [
                'village', 'desa', 'kelurahan', 'kecamatan', 'district in', 'regency in',
                'kabupaten', 'commune in', 'municipality in', 'canton in', 'river in', 'sungai',
                'mountain in', 'gunung', 'crater', 'asteroid', 'airport in', 'bandar udara',
                'lake in', 'danau', 'species', 'taxon', 'plant', 'tumbuhan',
                'city in', 'city of', 'town in', 'capital of', 'prefecture in', 'prefecture of',
                'island in', 'county in', 'family name', 'surname', 'given name', 'human settlement',
                'administrative division',
            ];

            foreach ($irrelevantKeywords as $kw) {
                if (str_contains($description, $kw)) {
                    return true;
                }
            }

            if (preg_match('/^(city|town|village|commune|municipality|capital|prefecture|county|district)\b/i', $description) === 1) {
                return true;
            }
        }

        return false;
    }

    private function fetchCandidateWebsite(string $candidateId): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::ENDPOINT, [
                    'action' => 'wbgetclaims',
                    'entity' => $candidateId,
                    'property' => 'P856',
                    'format' => 'json',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $rawClaims = (array) $response->json('claims.P856', []);
            $claims = [];
            foreach ($rawClaims as $claim) {
                if (is_array($claim)) {
                    $claims[] = $claim;
                }
            }

            if (empty($claims)) {
                return null;
            }

            return $this->pickBestUrl($claims);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $claims
     */
    private function pickBestUrl(array $claims): ?string
    {
        $candidates = [];

        foreach ($claims as $claim) {
            $rank = (string) ($claim['rank'] ?? 'normal');
            if ($rank === 'deprecated') {
                continue;
            }

            $rawUrl = (string) ($claim['mainsnak']['datavalue']['value'] ?? '');
            if ($rawUrl === '') {
                continue;
            }

            $normalized = $this->normalizeAndValidateUrl($rawUrl);
            if ($normalized === null) {
                continue;
            }

            $score = 0;
            if ($rank === 'preferred') {
                $score += 20;
            }

            $host = (string) parse_url($normalized, PHP_URL_HOST);
            $path = (string) (parse_url($normalized, PHP_URL_PATH) ?? '');

            if (str_ends_with($host, '.id') || str_ends_with($host, '.co.id')) {
                $score += 50;
            } elseif (str_contains($path, '/id/') || str_contains($path, '/id-id/') || str_ends_with($path, '/id')) {
                $score += 40;
            }

            // Slight tie-breaker preferring shorter/cleaner root domain URLs
            $score -= strlen($normalized) / 100;

            $candidates[] = [
                'url' => $normalized,
                'score' => $score,
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0]['url'];
    }

    private function normalizeAndValidateUrl(string $url): ?string
    {
        $url = trim($url);

        if (str_starts_with($url, 'http://')) {
            $url = 'https://'.substr($url, 7);
        } elseif (! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }

        $blockedHosts = [
            'wikipedia.org',
            'wikimedia.org',
            'wikidata.org',
            'facebook.com',
            'twitter.com',
            'x.com',
            'instagram.com',
            'linkedin.com',
            'youtube.com',
            'tiktok.com',
            't.me',
            'telegram.org',
        ];

        foreach ($blockedHosts as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                return null;
            }
        }

        // Exclude government / municipal / regional administration websites
        if (
            str_ends_with($host, '.gov')
            || str_ends_with($host, '.go.id')
            || str_contains($host, '.gov.')
            || str_contains($host, '.go.')
            || str_ends_with($host, '.lg.jp')
            || str_starts_with($host, 'city.')
            || str_starts_with($host, 'pemkab-')
            || str_starts_with($host, 'pemkot-')
        ) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if ($path === '/' || $path === null || $path === '') {
            $scheme = parse_url($url, PHP_URL_SCHEME) ?? 'https';

            return "{$scheme}://{$host}";
        }

        return rtrim($url, '/');
    }
}
