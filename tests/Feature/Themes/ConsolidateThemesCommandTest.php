<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Support\Facades\Http;

function fakeConsolidateLlm(array $groups): void
{
    LlmSetting::create(['base_url' => 'https://llm.test/v1', 'model' => 'm', 'api_key' => 'k', 'max_tokens' => 800, 'temperature' => 0.1, 'timeout_seconds' => 20]);
    Http::preventStrayRequests();
    Http::fake(['llm.test/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode(['groups' => $groups])]]],
    ])]);
}

function themeObservationFor(int $entityId, int $themeId, ?int $sourceItemId = null): ThemeObservation
{
    $source = Source::factory()->create();

    return ThemeObservation::create([
        'entity_id' => $entityId,
        'theme_id' => $themeId,
        'source_id' => $source->id,
        'source_item_id' => $sourceItemId,
        'sentiment' => SentimentClass::Negative,
        'extractor' => 'llm',
    ]);
}

it('merges member themes onto the highest-count member and renames it to the canonical label', function () {
    config(['themes.extractor' => 'llm']);
    $boros = Theme::create(['slug' => 'baterai-boros', 'display_label' => 'Baterai boros', 'canonical_key' => 'baterai-boros']);
    $cepat = Theme::create(['slug' => 'baterai-cepat-habis', 'display_label' => 'Baterai cepat habis', 'canonical_key' => 'baterai-cepat-habis']);
    $entity = Entity::factory()->create();
    themeObservationFor($entity->id, $boros->id);
    foreach (range(1, 3) as $i) {
        themeObservationFor($entity->id, $cepat->id);
    }

    fakeConsolidateLlm([
        ['canonical_label' => 'baterai cepat habis', 'member_ids' => [$boros->id, $cepat->id]],
    ]);

    $this->artisan('themes:consolidate')->assertSuccessful();

    expect(Theme::whereKey($boros->id)->exists())->toBeFalse()
        ->and(ThemeObservation::where('theme_id', $cepat->id)->count())->toBe(4)
        ->and($cepat->fresh()->display_label)->toBe('Baterai cepat habis');
});

it('deletes a colliding duplicate rather than violating the unique constraint', function () {
    config(['themes.extractor' => 'llm']);
    $a = Theme::create(['slug' => 'a', 'display_label' => 'A', 'canonical_key' => 'a']);
    $b = Theme::create(['slug' => 'b', 'display_label' => 'B', 'canonical_key' => 'b']);
    $entity = Entity::factory()->create();
    $sharedItem = SourceItem::factory()->create();

    // Same (entity, source_item) opinion was already tagged with BOTH themes by two
    // separate extraction runs — merging a's observation onto b's theme_id must not
    // crash on the (entity_id, theme_id, source_item_id) unique constraint.
    themeObservationFor($entity->id, $b->id, $sharedItem->id);
    themeObservationFor($entity->id, $b->id); // extra count so B is the higher-count member -> canonical
    themeObservationFor($entity->id, $a->id, $sharedItem->id);

    fakeConsolidateLlm([
        ['canonical_label' => 'B', 'member_ids' => [$a->id, $b->id]],
    ]);

    $this->artisan('themes:consolidate')->assertSuccessful();

    expect(ThemeObservation::where('entity_id', $entity->id)->where('source_item_id', $sharedItem->id)->count())->toBe(1)
        ->and(Theme::whereKey($a->id)->exists())->toBeFalse();
});

it('ignores a group with fewer than 2 valid member_ids', function () {
    config(['themes.extractor' => 'llm']);
    $only = Theme::create(['slug' => 'only', 'display_label' => 'Only', 'canonical_key' => 'only']);
    $other = Theme::create(['slug' => 'other', 'display_label' => 'Other', 'canonical_key' => 'other']);
    $entity = Entity::factory()->create();
    themeObservationFor($entity->id, $only->id);
    themeObservationFor($entity->id, $other->id);

    // 999999 isn't a real theme id — after intersecting with real themes, this group
    // has only 1 valid member and must be skipped, not merged.
    fakeConsolidateLlm([
        ['canonical_label' => 'Only', 'member_ids' => [$only->id, 999999]],
    ]);

    $this->artisan('themes:consolidate')
        ->expectsOutputToContain('Merged 0 duplicate theme(s)')
        ->assertSuccessful();

    expect(Theme::whereKey($only->id)->exists())->toBeTrue();
});

it('writes nothing on --dry-run', function () {
    config(['themes.extractor' => 'llm']);
    $boros = Theme::create(['slug' => 'baterai-boros', 'display_label' => 'Baterai boros', 'canonical_key' => 'baterai-boros']);
    $cepat = Theme::create(['slug' => 'baterai-cepat-habis', 'display_label' => 'Baterai cepat habis', 'canonical_key' => 'baterai-cepat-habis']);
    $entity = Entity::factory()->create();
    themeObservationFor($entity->id, $boros->id);
    themeObservationFor($entity->id, $cepat->id);

    fakeConsolidateLlm([
        ['canonical_label' => 'baterai cepat habis', 'member_ids' => [$boros->id, $cepat->id]],
    ]);

    $this->artisan('themes:consolidate', ['--dry-run' => true])->assertSuccessful();

    expect(Theme::whereKey($boros->id)->exists())->toBeTrue()
        ->and(Theme::whereKey($cepat->id)->exists())->toBeTrue();
});

it('chunks the prompt when more themes exist than the per-call chunk size', function () {
    config(['themes.extractor' => 'llm']);
    $entity = Entity::factory()->create();
    foreach (range(1, 160) as $i) {
        $theme = Theme::create(['slug' => "t{$i}", 'display_label' => "T{$i}", 'canonical_key' => "t{$i}"]);
        themeObservationFor($entity->id, $theme->id);
    }
    fakeConsolidateLlm([]);

    $this->artisan('themes:consolidate')->assertSuccessful();

    Http::assertSentCount(2);
});

it('refuses to run when the extractor is not llm', function () {
    config(['themes.extractor' => 'keyword']);

    $this->artisan('themes:consolidate')->assertSuccessful();

    Http::fake();
    Http::assertNothingSent();
});

it('runs themes:rebuild-aggregates afterward when --rebuild is passed and something merged', function () {
    config(['themes.extractor' => 'llm']);
    $boros = Theme::create(['slug' => 'baterai-boros', 'display_label' => 'Baterai boros', 'canonical_key' => 'baterai-boros']);
    $cepat = Theme::create(['slug' => 'baterai-cepat-habis', 'display_label' => 'Baterai cepat habis', 'canonical_key' => 'baterai-cepat-habis']);
    $entity = Entity::factory()->create();
    themeObservationFor($entity->id, $boros->id);
    // cepat has more observations, so it deterministically wins the canonical tie-break.
    foreach (range(1, 3) as $i) {
        themeObservationFor($entity->id, $cepat->id);
    }

    fakeConsolidateLlm([
        ['canonical_label' => 'baterai cepat habis', 'member_ids' => [$boros->id, $cepat->id]],
    ]);

    $this->artisan('themes:consolidate', ['--rebuild' => true])->assertSuccessful();

    expect(EntityThemeSnapshot::where('entity_id', $entity->id)->where('theme_id', $cepat->id)->where('observation_count', 4)->exists())->toBeTrue();
});
