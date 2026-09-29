<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Themes\Models\EntityThemeSummary;

function purgeFixtureObservation(Entity $entity, Source $source, ?string $text): SentimentObservation
{
    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    if ($text !== null) {
        app(RawPayloadStorage::class)->store($source, $text, $item, 'text/plain');
    }

    return SentimentObservation::factory()->create([
        'entity_id' => $entity->id,
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'sentiment' => SentimentClass::Positive,
        'observed_at' => now(),
    ]);
}

function purgeFixtureGaruda(): array
{
    $garuda = Entity::factory()->create(['name' => 'Garuda Indonesia', 'slug' => 'garuda-indonesia']);
    EntityAlias::factory()->create(['entity_id' => $garuda->id, 'alias' => 'GA', 'normalized_alias' => 'ga']);
    $source = Source::factory()->create();

    $genuine = purgeFixtureObservation($garuda, $source, 'Pelayanan Garuda Indonesia kali ini sangat bagus');
    $wrong = purgeFixtureObservation($garuda, $source, 'lotionnya ga lengket dan wangi enak');
    $expired = purgeFixtureObservation($garuda, $source, null);

    return [$garuda, $genuine, $wrong, $expired];
}

it('deletes opinions the matcher would no longer attribute and keeps genuine and unverifiable ones', function () {
    [$garuda, $genuine, $wrong, $expired] = purgeFixtureGaruda();
    EntityThemeSummary::create(['entity_id' => $garuda->id, 'summary' => 'x', 'theme_notes' => [], 'opinion_count' => 3, 'generated_at' => now()]);

    $this->artisan('entities:purge-mismatched-opinions', ['--entity' => ['garuda-indonesia']])->assertSuccessful();

    expect(SentimentObservation::query()->pluck('id')->all())->toEqualCanonicalizing([$genuine->id, $expired->id])
        ->and(SentimentSnapshot::query()->where('entity_id', $garuda->id)->where('period', 'all')->value('opinion_count'))->toBe(2)
        ->and(EntityThemeSummary::query()->where('entity_id', $garuda->id)->exists())->toBeFalse();
});

it('also deletes unverifiable opinions when the entity is named for it', function () {
    [, $genuine] = purgeFixtureGaruda();

    $this->artisan('entities:purge-mismatched-opinions', [
        '--entity' => ['garuda-indonesia'],
        '--purge-unverifiable' => ['garuda-indonesia'],
    ])->assertSuccessful();

    expect(SentimentObservation::query()->pluck('id')->all())->toBe([$genuine->id]);
});

it('changes nothing on a dry run', function () {
    purgeFixtureGaruda();

    $this->artisan('entities:purge-mismatched-opinions', ['--entity' => ['garuda-indonesia'], '--dry-run' => true])->assertSuccessful();

    expect(SentimentObservation::count())->toBe(3);
});
