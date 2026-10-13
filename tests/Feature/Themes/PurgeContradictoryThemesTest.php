<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sources\Models\Source;
use App\Domains\Themes\Jobs\RebuildEntityAggregatesJob;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Support\Facades\Queue;

it('deletes negative observations on praising labels only', function () {
    Queue::fake();
    $entity = Entity::factory()->create(['category_id' => Category::factory()->create()->id]);
    $source = Source::factory()->create();
    $praise = Theme::create(['slug' => 'kamera-bagus', 'display_label' => 'Kamera bagus', 'canonical_key' => 'kamera-bagus']);
    $lost = Theme::create(['slug' => 'kamera-kalah-bagus', 'display_label' => 'Kamera kalah bagus', 'canonical_key' => 'kamera-kalah-bagus']);
    $complaint = Theme::create(['slug' => 'baterai-habis', 'display_label' => 'Baterai cepat habis', 'canonical_key' => 'baterai-habis']);

    foreach ([[$praise, 'positive'], [$praise, 'negative'], [$lost, 'negative'], [$complaint, 'negative']] as [$theme, $sentiment]) {
        ThemeObservation::create(['entity_id' => $entity->id, 'theme_id' => $theme->id, 'source_id' => $source->id, 'sentiment' => SentimentClass::from($sentiment)]);
    }

    $this->artisan('themes:purge-contradictions', ['--dry-run' => true])->assertSuccessful();
    expect(ThemeObservation::count())->toBe(4);

    $this->artisan('themes:purge-contradictions')->assertSuccessful();

    expect(ThemeObservation::count())->toBe(3)
        ->and(ThemeObservation::where('theme_id', $praise->id)->pluck('sentiment')->map->value->all())->toBe(['positive']);
    Queue::assertPushed(RebuildEntityAggregatesJob::class, 1);
});
