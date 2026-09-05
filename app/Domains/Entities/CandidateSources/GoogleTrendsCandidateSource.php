<?php

namespace App\Domains\Entities\CandidateSources;

use App\Domains\Entities\Contracts\EntityCandidateSource;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

/**
 * Google's free Daily Trends RSS for Indonesia. General trending topics, not
 * brand/product-filtered — the LLM-enrichment + admin-review gate every
 * other source already goes through does that filtering (drops politics,
 * entertainment, etc.). Unofficial/undocumented endpoint: same risk class
 * as any other scraped source in this project.
 */
class GoogleTrendsCandidateSource implements EntityCandidateSource
{
    private const FEED_URL = 'https://trends.google.com/trending/rss?geo=ID';

    public function sourceType(): string
    {
        return 'google_trends';
    }

    /**
     * @return list<array{raw_term: string, weight: int}>
     */
    public function discover(): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'SuaraNetijen/1.0 (+https://suaranetijen.id/sources)'])
            ->get(self::FEED_URL);
        $response->throw();

        $xml = @simplexml_load_string($response->body());
        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $candidates = [];
        $namespaces = $xml->getNamespaces(true);
        $htNamespace = $namespaces['ht'] ?? 'http://trends.google.com/trending/rss';

        foreach ($xml->channel->item as $item) {
            $title = trim((string) $item->title);
            if ($title === '') {
                continue;
            }

            $traffic = (string) $item->children($htNamespace)->approx_traffic;
            $weight = (int) preg_replace('/\D/', '', $traffic);

            $candidates[] = ['raw_term' => $title, 'weight' => max(1, $weight)];
        }

        return $candidates;
    }
}
