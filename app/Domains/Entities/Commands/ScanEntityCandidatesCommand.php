<?php

namespace App\Domains\Entities\Commands;

use App\Domains\Entities\CandidateSources\DailySocialCandidateSource;
use App\Domains\Entities\CandidateSources\GoogleTrendsCandidateSource;
use App\Domains\Entities\CandidateSources\SearchQueryCandidateSource;
use App\Domains\Entities\CandidateSources\WikidataCandidateSource;
use App\Domains\Entities\Services\EntityCandidateAggregator;
use App\Domains\Entities\Services\EntityCandidateEnricher;
use Illuminate\Console\Command;

class ScanEntityCandidatesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'entities:scan-candidates';

    /**
     * @var string
     */
    protected $description = 'Scan search queries, unmatched mentions, and external feeds for new brand/product candidates and enrich them for admin review';

    public function handle(
        SearchQueryCandidateSource $searchQuery,
        WikidataCandidateSource $wikidata,
        DailySocialCandidateSource $dailySocial,
        GoogleTrendsCandidateSource $googleTrends,
        EntityCandidateEnricher $enricher
    ): int {
        $aggregator = new EntityCandidateAggregator(
            [$searchQuery, $wikidata, $dailySocial, $googleTrends],
            $enricher
        );

        $created = $aggregator->scan();
        $this->info("Created {$created} new entity candidate(s) awaiting admin review.");

        return self::SUCCESS;
    }
}
