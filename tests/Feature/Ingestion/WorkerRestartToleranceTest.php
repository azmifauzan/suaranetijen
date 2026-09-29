<?php

use App\Domains\Ingestion\Jobs\ClassifySentimentJob;
use App\Domains\Ingestion\Jobs\ExtractCandidateOpinionsJob;
use App\Domains\Ingestion\Jobs\MatchEntitiesJob;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Jobs\UpsertSentimentObservationJob;
use App\Domains\Sources\Models\SourceDocument;

// Horizon runs the supervisors with tries=1. A job whose worker is stopped mid-run is redelivered
// after retry_after and would then fail with MaxAttemptsExceeded (4-7 MatchEntitiesJob per deploy).
// These jobs are idempotent, so they get extra attempts for redelivery, while a real exception
// still fails them on the first throw (maxExceptions = 1).
it('lets idempotent pipeline jobs be redelivered after a worker restart without failing them', function (object $job) {
    expect($job->tries)->toBe(3)
        ->and($job->maxExceptions)->toBe(1);
})->with([
    'match entities' => fn () => new MatchEntitiesJob(1),
    'classify sentiment' => fn () => new ClassifySentimentJob(1, 1),
    'upsert sentiment observation' => fn () => new UpsertSentimentObservationJob(1, 1, SentimentClass::Positive),
    'extract candidate opinions' => fn () => new ExtractCandidateOpinionsJob(new SourceDocument, '{}'),
]);
