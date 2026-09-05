<?php

namespace App\Domains\Entities\CandidateSources;

use App\Domains\Entities\Contracts\EntityCandidateSource;
use App\Domains\Entities\Services\TextNormalizer;
use Illuminate\Support\Facades\Http;

/**
 * Free, structured, no-auth SPARQL feed of recently released smartphone and
 * vehicle models — covers Smartphone/Mobil/Motor in one query instead of
 * one adapter per manufacturer. Global data, not Indonesia-filtered: real
 * market relevance is still gated by the same LLM-enrichment + admin-review
 * step every other source goes through.
 */
class WikidataCandidateSource implements EntityCandidateSource
{
    private const ENDPOINT = 'https://query.wikidata.org/sparql';

    public function sourceType(): string
    {
        return 'wikidata';
    }

    /**
     * @return list<array{raw_term: string, weight: int}>
     */
    public function discover(): array
    {
        $since = now()->subDays(180)->format('Y-m-d');
        $query = <<<SPARQL
            SELECT ?itemLabel WHERE {
              { ?item wdt:P31/wdt:P279* wd:Q19723451. }
              UNION
              { ?item wdt:P31/wdt:P279* wd:Q1420. }
              ?item wdt:P577 ?pubdate.
              FILTER(?pubdate > "{$since}"^^xsd:dateTime)
              SERVICE wikibase:label { bd:serviceParam wikibase:language "en,id,mul". }
            }
            LIMIT 200
            SPARQL;

        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'SuaraNetijen/1.0 (+https://suaranetijen.id/sources)'])
            ->get(self::ENDPOINT, ['query' => $query, 'format' => 'json']);
        $response->throw();

        $bindings = (array) $response->json('results.bindings', []);
        $terms = [];
        foreach ($bindings as $binding) {
            $label = (string) ($binding['itemLabel']['value'] ?? '');
            // The label service falls back to the raw entity ID (e.g. "Q141025060")
            // when no label exists in any requested language — not a real name,
            // and not worth an LLM enrichment call.
            if (preg_match('/^Q\d+$/', $label) === 1) {
                continue;
            }

            $normalized = TextNormalizer::normalize($label);
            if ($normalized !== '') {
                $terms[$normalized] = true;
            }
        }

        return array_map(fn (string $term): array => ['raw_term' => $term, 'weight' => 1], array_keys($terms));
    }
}
