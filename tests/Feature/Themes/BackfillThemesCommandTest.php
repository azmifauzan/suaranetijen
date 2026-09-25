<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Themes\Jobs\ExtractThemesJob;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Support\Facades\Queue;

it('dispatches theme extraction for existing observations whose raw payload still exists', function () {
    Queue::fake();

    $entity = Entity::factory()->create();
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    app(RawPayloadStorage::class)->store($source, 'Servernya ngebut banget!', $item, 'text/plain');
    SentimentObservation::factory()->create([
        'entity_id' => $entity->id,
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'sentiment' => SentimentClass::Positive,
    ]);

    $expiredItem = SourceItem::factory()->create(['source_id' => $source->id]);
    SentimentObservation::factory()->create([
        'entity_id' => $entity->id,
        'source_id' => $source->id,
        'source_item_id' => $expiredItem->id,
        'sentiment' => SentimentClass::Positive,
    ]);

    $this->artisan('themes:backfill')->assertSuccessful();

    Queue::assertPushed(ExtractThemesJob::class, 1);
    Queue::assertPushed(ExtractThemesJob::class, fn ($job) => $job->entityId === $entity->id
        && $job->sourceItemId === $item->id
        && $job->text === 'Servernya ngebut banget!');
});

function backfillObservation(string $text, ?Entity $entity = null): SentimentObservation
{
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    app(RawPayloadStorage::class)->store($source, $text, $item, 'text/plain');

    return SentimentObservation::factory()->create([
        'entity_id' => ($entity ?? Entity::factory()->create())->id,
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'sentiment' => SentimentClass::Positive,
    ]);
}

it('paces llm backfill dispatch, skips short and already extracted items, and trims text', function () {
    Queue::fake();
    config(['themes.extractor' => 'llm', 'themes.llm_min_chars' => 30]);

    $long = 'Baterainya cepat habis sejak update kemarin, kecewa banget.';
    $first = backfillObservation($long);
    $second = backfillObservation($long.' Lagi.');
    backfillObservation('mantap');
    $done = backfillObservation($long.' Sudah.');
    $huge = backfillObservation(str_repeat('a', 9000));
    ThemeObservation::create([
        'entity_id' => $done->entity_id,
        'theme_id' => Theme::create(['slug' => 'x', 'display_label' => 'X', 'canonical_key' => 'x'])->id,
        'source_id' => $done->source_id,
        'source_item_id' => $done->source_item_id,
        'sentiment' => SentimentClass::Positive,
        'extractor' => 'llm',
    ]);

    $this->artisan('themes:backfill', ['--per-minute' => 2])->assertSuccessful();

    Queue::assertPushed(ExtractThemesJob::class, 3);
    $delays = [];
    Queue::assertPushed(ExtractThemesJob::class, function (ExtractThemesJob $job) use (&$delays) {
        $delays[$job->sourceItemId] = (int) now()->diffInSeconds($job->delay, false);

        return true;
    });
    // 2 per minute -> one job every 30s: 0s, 30s, 60s
    expect(collect($delays)->sort()->values()->map(fn ($d) => (int) round($d / 30) * 30)->all())->toBe([0, 30, 60]);
    Queue::assertPushed(ExtractThemesJob::class, fn ($job) => $job->sourceItemId === $huge->source_item_id
        && mb_strlen($job->text) === 4000);
    Queue::assertNotPushed(ExtractThemesJob::class, fn ($job) => $job->sourceItemId === $done->source_item_id);
});

it('does nothing but count on --dry-run', function () {
    Queue::fake();
    config(['themes.extractor' => 'llm']);
    backfillObservation('Baterainya cepat habis sejak update kemarin, kecewa banget.');

    $this->artisan('themes:backfill', ['--dry-run' => true])
        ->expectsOutputToContain('Would dispatch theme extraction for 1 observation')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
