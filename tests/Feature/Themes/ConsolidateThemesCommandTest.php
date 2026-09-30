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
    $a = Theme::create(['slug' => 'a', 'display_label' => 'Layar bening', 'canonical_key' => 'a']);
    $b = Theme::create(['slug' => 'b', 'display_label' => 'Layar tajam', 'canonical_key' => 'b']);
    $entity = Entity::factory()->create();
    $sharedItem = SourceItem::factory()->create();

    // Same (entity, source_item) opinion was already tagged with BOTH themes by two
    // separate extraction runs — merging a's observation onto b's theme_id must not
    // crash on the (entity_id, theme_id, source_item_id) unique constraint.
    themeObservationFor($entity->id, $b->id, $sharedItem->id);
    themeObservationFor($entity->id, $b->id); // extra count so B is the higher-count member -> canonical
    themeObservationFor($entity->id, $a->id, $sharedItem->id);

    fakeConsolidateLlm([
        ['canonical_label' => 'Layar tajam', 'member_ids' => [$a->id, $b->id]],
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

it('drops a group member that an earlier group in the same run already merged away', function () {
    config(['themes.extractor' => 'llm']);
    $a = Theme::create(['slug' => 'a', 'display_label' => 'Layar bening', 'canonical_key' => 'a']);
    $b = Theme::create(['slug' => 'b', 'display_label' => 'Layar tajam', 'canonical_key' => 'b']);
    $c = Theme::create(['slug' => 'c', 'display_label' => 'Layar terang', 'canonical_key' => 'c']);
    $entity = Entity::factory()->create();
    themeObservationFor($entity->id, $a->id);
    foreach (range(1, 3) as $i) {
        themeObservationFor($entity->id, $b->id);
    }
    themeObservationFor($entity->id, $c->id);

    // The LLM put theme A in two different "synonym" groups within one response — a
    // real observed failure mode. Group 1 merges A into B and deletes A; group 2's
    // reference to A is now stale and must be dropped, not crash on a foreign key.
    fakeConsolidateLlm([
        ['canonical_label' => 'Layar tajam', 'member_ids' => [$a->id, $b->id]],
        ['canonical_label' => 'Layar bening', 'member_ids' => [$a->id, $c->id]],
    ]);

    $this->artisan('themes:consolidate')->assertSuccessful();

    expect(Theme::whereKey($a->id)->exists())->toBeFalse()
        ->and(Theme::whereKey($c->id)->exists())->toBeTrue()
        ->and(ThemeObservation::where('theme_id', $b->id)->count())->toBe(4);
});

it('groups the themes of one entity together with --per-entity', function () {
    config(['themes.extractor' => 'llm', 'themes.min_entity_opinions' => 2]);
    $murah = Theme::create(['slug' => 'harga-murah', 'display_label' => 'Harga murah', 'canonical_key' => 'harga-murah']);
    $murahFe = Theme::create(['slug' => 'harga-s24-fe-murah', 'display_label' => 'Harga s24 fe murah', 'canonical_key' => 'harga-s24-fe-murah']);
    $entity = Entity::factory()->create();
    $thin = Entity::factory()->create();
    themeObservationFor($entity->id, $murah->id);
    themeObservationFor($entity->id, $murahFe->id);
    themeObservationFor($thin->id, $murah->id);

    fakeConsolidateLlm([
        ['canonical_label' => 'harga murah', 'member_ids' => [$murah->id, $murahFe->id]],
    ]);

    $this->artisan('themes:consolidate --per-entity')->assertSuccessful();

    expect(Theme::whereKey($murahFe->id)->exists())->toBeFalse()
        ->and(ThemeObservation::where('entity_id', $entity->id)->where('theme_id', $murah->id)->count())->toBe(2);
    Http::assertSentCount(1);
});

it('skips an entity whose LLM call fails and keeps going', function () {
    config(['themes.extractor' => 'llm', 'themes.min_entity_opinions' => 2]);
    $a = Theme::create(['slug' => 'a', 'display_label' => 'Layar bening', 'canonical_key' => 'a']);
    $b = Theme::create(['slug' => 'b', 'display_label' => 'Layar tajam', 'canonical_key' => 'b']);
    $first = Entity::factory()->create();
    $second = Entity::factory()->create();
    foreach ([$first, $second] as $entity) {
        themeObservationFor($entity->id, $a->id);
        themeObservationFor($entity->id, $b->id);
    }

    LlmSetting::create(['base_url' => 'https://llm.test/v1', 'model' => 'm', 'api_key' => 'k', 'max_tokens' => 800, 'temperature' => 0.1, 'timeout_seconds' => 20]);
    Http::fake(['llm.test/*' => Http::sequence()
        ->push('timeout', 500)
        ->push(['choices' => [['message' => ['content' => json_encode(['groups' => [
            ['canonical_label' => 'Layar bening', 'member_ids' => [$a->id, $b->id]],
        ]])]]]]),
    ]);

    $this->artisan('themes:consolidate --per-entity')->assertSuccessful();

    expect(Theme::whereKey($b->id)->exists())->toBeFalse();
});

it('merges themes that only differ by the entity name and variant words with --by-name, without calling the LLM', function () {
    config(['themes.extractor' => 'llm', 'themes.min_entity_opinions' => 2]);
    Http::preventStrayRequests();
    $entity = Entity::factory()->create(['name' => 'Samsung Galaxy S24']);
    $murah = Theme::create(['slug' => 'harga-murah', 'display_label' => 'Harga murah', 'canonical_key' => 'harga-murah']);
    $murahFe = Theme::create(['slug' => 'harga-s24-fe-murah', 'display_label' => 'Harga samsung s24 fe murah', 'canonical_key' => 'harga-samsung-s24-fe-murah']);
    $mahal = Theme::create(['slug' => 'harga-mahal', 'display_label' => 'Harga mahal', 'canonical_key' => 'harga-mahal']);
    $lone = Theme::create(['slug' => 'baterai-s24', 'display_label' => 'Baterai s24', 'canonical_key' => 'baterai-s24']);
    $loneOther = Theme::create(['slug' => 'baterai-fe', 'display_label' => 'Baterai fe', 'canonical_key' => 'baterai-fe']);
    foreach ([$murah, $murahFe, $mahal, $lone, $loneOther] as $theme) {
        themeObservationFor($entity->id, $theme->id);
    }

    $this->artisan('themes:consolidate --by-name')->assertSuccessful();

    expect(Theme::whereKey($murahFe->id)->exists())->toBeFalse()
        ->and(ThemeObservation::where('entity_id', $entity->id)->where('theme_id', $murah->id)->count())->toBe(2)
        ->and(Theme::whereKey($mahal->id)->exists())->toBeTrue()
        ->and(Theme::whereKey($lone->id)->exists())->toBeTrue()
        ->and(Theme::whereKey($loneOther->id)->exists())->toBeTrue();
    Http::assertNothingSent();
});

it('does not merge a theme of opposite polarity even when the LLM groups it', function () {
    config(['themes.extractor' => 'llm']);
    $murah = Theme::create(['slug' => 'harga-murah', 'display_label' => 'Harga murah', 'canonical_key' => 'harga-murah']);
    $terjangkau = Theme::create(['slug' => 'harga-terjangkau', 'display_label' => 'Harga terjangkau', 'canonical_key' => 'harga-terjangkau']);
    $mahal = Theme::create(['slug' => 'harga-mahal', 'display_label' => 'Harga mahal', 'canonical_key' => 'harga-mahal']);
    $entity = Entity::factory()->create();
    foreach ([$murah, $terjangkau] as $theme) {
        themeObservationFor($entity->id, $theme->id)->update(['sentiment' => SentimentClass::Positive]);
    }
    themeObservationFor($entity->id, $murah->id)->update(['sentiment' => SentimentClass::Positive]);
    themeObservationFor($entity->id, $mahal->id);

    fakeConsolidateLlm([
        ['canonical_label' => 'harga murah', 'member_ids' => [$murah->id, $terjangkau->id, $mahal->id]],
    ]);

    $this->artisan('themes:consolidate')->assertSuccessful();

    expect(Theme::whereKey($mahal->id)->exists())->toBeTrue()
        ->and(Theme::whereKey($terjangkau->id)->exists())->toBeFalse()
        ->and(ThemeObservation::where('theme_id', $murah->id)->count())->toBe(3);
});

it('keeps only group members that share a content word with the largest member', function () {
    config(['themes.extractor' => 'llm']);
    $cs = Theme::create(['slug' => 'cs-lambat-merespons', 'display_label' => 'Cs lambat merespons', 'canonical_key' => 'cs-lambat-merespons']);
    $csLain = Theme::create(['slug' => 'cs-sulit-dihubungi-merespons', 'display_label' => 'Cs sulit dihubungi merespons', 'canonical_key' => 'cs-sulit-dihubungi-merespons']);
    $harga = Theme::create(['slug' => 'harga-kurang-seimbang', 'display_label' => 'Harga kurang seimbang', 'canonical_key' => 'harga-kurang-seimbang']);
    $entity = Entity::factory()->create();
    foreach ([$cs, $cs, $csLain, $harga] as $theme) {
        themeObservationFor($entity->id, $theme->id);
    }

    fakeConsolidateLlm([
        ['canonical_label' => 'cs lambat merespons', 'member_ids' => [$cs->id, $csLain->id, $harga->id]],
    ]);

    $this->artisan('themes:consolidate')->assertSuccessful();

    expect(Theme::whereKey($harga->id)->exists())->toBeTrue()
        ->and(Theme::whereKey($csLain->id)->exists())->toBeFalse();
});

it('uses the largest member label when the LLM canonical label is unrelated to it', function () {
    config(['themes.extractor' => 'llm']);
    $tanggap = Theme::create(['slug' => 'cs-cepat-tanggap', 'display_label' => 'Cs cepat tanggap', 'canonical_key' => 'cs-cepat-tanggap']);
    $komplain = Theme::create(['slug' => 'komplain-cs-tanggap', 'display_label' => 'Komplain cs tanggap', 'canonical_key' => 'komplain-cs-tanggap']);
    $entity = Entity::factory()->create();
    foreach ([$tanggap, $tanggap, $komplain] as $theme) {
        themeObservationFor($entity->id, $theme->id);
    }

    fakeConsolidateLlm([
        ['canonical_label' => 'layanan lambat sekali', 'member_ids' => [$tanggap->id, $komplain->id]],
    ]);

    $this->artisan('themes:consolidate')->assertSuccessful();

    expect($tanggap->fresh()->display_label)->toBe('Cs cepat tanggap')
        ->and(Theme::whereKey($komplain->id)->exists())->toBeFalse();
});
