<?php

namespace App\Domains\Entities\CandidateSources;

use App\Domains\Entities\Contracts\EntityCandidateSource;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

/**
 * DailySocial's RSS feed surfaces new Indonesian digital-economy brands
 * (funding rounds, market entries) before they'd show up in our own
 * zero-result search queries.
 */
class DailySocialCandidateSource implements EntityCandidateSource
{
    private const FEED_URL = 'https://dailysocial.id/feed';

    public function sourceType(): string
    {
        return 'daily_social';
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
        foreach ($xml->channel->item as $item) {
            $title = trim((string) $item->title);
            if ($title !== '') {
                $candidates[] = ['raw_term' => $title, 'weight' => 1];
            }
        }

        return $candidates;
    }
}
