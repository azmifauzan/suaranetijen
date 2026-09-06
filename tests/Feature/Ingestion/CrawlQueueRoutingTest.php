<?php

use App\Domains\Ingestion\Jobs\FetchSourceDocumentJob;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;

it('routes a normal source\'s fetch job to the shared crawl queue', function () {
    $source = Source::factory()->create(['crawl_policy' => ['rate_limit_per_minute' => 30]]);
    $document = SourceDocument::factory()->create(['source_id' => $source->id]);

    $job = new FetchSourceDocumentJob($document);

    expect($job->queue)->toBe('crawl');
});

it('routes a source with a crawl_policy queue override to its own dedicated queue', function () {
    // YouTube's fan-out was starving low-rate sources sharing the plain
    // 'crawl' queue (confirmed live 5-6 Sep 2026) — its Source row carries
    // an explicit queue override instead of hardcoding "youtube" in the job.
    $source = Source::factory()->create([
        'key' => 'youtube',
        'crawl_policy' => ['rate_limit_per_minute' => 30, 'queue' => 'crawl-youtube'],
    ]);
    $document = SourceDocument::factory()->create(['source_id' => $source->id]);

    $job = new FetchSourceDocumentJob($document);

    expect($job->queue)->toBe('crawl-youtube');
});
