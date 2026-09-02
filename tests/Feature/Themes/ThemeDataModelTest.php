<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Enums\SourceType;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeAlias;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Database\UniqueConstraintViolationException;

test('theme and theme aliases relationships function correctly', function () {
    $theme = Theme::create([
        'slug' => 'murah',
        'display_label' => 'Murah',
        'canonical_key' => 'price_affordable',
    ]);

    $alias = ThemeAlias::create([
        'theme_id' => $theme->id,
        'alias' => 'terjangkau',
        'normalized_alias' => 'terjangkau',
    ]);

    expect($theme->aliases)->toHaveCount(1)
        ->and($theme->aliases->first()->alias)->toBe('terjangkau')
        ->and($alias->theme->id)->toBe($theme->id);
});

test('PRD AC 12: duplicate source item does not create duplicate theme observation', function () {
    $category = Category::create([
        'name' => 'Cloud & Hosting',
        'slug' => 'cloud-hosting',
        'status' => CategoryStatus::Active,
    ]);

    $entity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Brand,
        'name' => 'Biznet Gio',
        'slug' => 'biznet-gio',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    $source = Source::create([
        'key' => 'dwh',
        'name' => 'Diskusi Web Hosting',
        'adapter' => 'DiskusiWebHostingAdapter',
        'source_type' => SourceType::Forum,
        'enabled' => true,
        'priority' => 10,
        'health_state' => SourceHealthState::Healthy,
    ]);

    $doc = SourceDocument::create([
        'source_id' => $source->id,
        'external_id' => 'thread-123',
        'discovered_at' => now(),
    ]);

    $item = SourceItem::create([
        'source_id' => $source->id,
        'source_document_id' => $doc->id,
        'external_id' => 'post-456',
        'content_hash' => hash('sha256', 'Harga sangat murah dan terjangkau'),
    ]);

    $theme = Theme::create([
        'slug' => 'murah',
        'display_label' => 'Murah',
        'canonical_key' => 'price_affordable',
    ]);

    // First observation for this entity, theme, and source item
    ThemeObservation::create([
        'entity_id' => $entity->id,
        'theme_id' => $theme->id,
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'sentiment' => SentimentClass::Positive,
        'confidence' => 0.95,
        'observed_at' => now(),
    ]);

    expect(ThemeObservation::count())->toBe(1);

    // Replay / retry against the same item must violate unique constraint (dedup guarantee)
    expect(function () use ($entity, $theme, $source, $item) {
        ThemeObservation::create([
            'entity_id' => $entity->id,
            'theme_id' => $theme->id,
            'source_id' => $source->id,
            'source_item_id' => $item->id,
            'sentiment' => SentimentClass::Positive,
            'confidence' => 0.95,
            'observed_at' => now(),
        ]);
    })->toThrow(UniqueConstraintViolationException::class);
});
