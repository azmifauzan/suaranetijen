<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityCandidate;
use App\Domains\Sentiment\Models\SentimentObservation;

it('gives a spaced slug a proper slug and suffixes on collision', function () {
    $clean = Entity::factory()->create(['name' => 'Oppo Find N3', 'slug' => 'oppo-find-n3']);
    $spaced = Entity::factory()->create(['name' => 'Oppo Find N3 Flip', 'slug' => 'oppo find n3 flip']);
    $collides = Entity::factory()->create(['name' => 'Oppo Find N3-Flip', 'slug' => 'oppo find n3-flip']);

    $this->artisan('entities:fix-spaced-slugs')->assertSuccessful();

    expect($spaced->fresh()->slug)->toBe('oppo-find-n3-flip')
        ->and($collides->fresh()->slug)->toBe('oppo-find-n3-flip-2')
        ->and($clean->fresh()->slug)->toBe('oppo-find-n3');
});

it('drops a spaced duplicate that has no opinions and repoints its candidate link', function () {
    $clean = Entity::factory()->create(['name' => 'Samsung Galaxy S24', 'slug' => 'samsung-galaxy-s24']);
    $spaced = Entity::factory()->create(['name' => 'Samsung Galaxy S24', 'slug' => 'samsung galaxy s24']);
    $candidate = EntityCandidate::factory()->create(['entity_id' => $spaced->id]);

    $this->artisan('entities:fix-spaced-slugs')->assertSuccessful();

    expect(Entity::query()->whereKey($spaced->id)->exists())->toBeFalse()
        ->and($candidate->fresh()->entity_id)->toBe($clean->id);
});

it('leaves a spaced duplicate that has opinions for a manual merge', function () {
    Entity::factory()->create(['name' => 'Samsung Galaxy S24', 'slug' => 'samsung-galaxy-s24']);
    $spaced = Entity::factory()->create(['name' => 'Samsung Galaxy S24', 'slug' => 'samsung galaxy s24']);
    SentimentObservation::factory()->create(['entity_id' => $spaced->id]);

    $this->artisan('entities:fix-spaced-slugs')->assertSuccessful();

    expect(Entity::query()->whereKey($spaced->id)->exists())->toBeTrue()
        ->and($spaced->fresh()->slug)->toBe('samsung galaxy s24');
});

it('writes nothing on --dry-run', function () {
    $spaced = Entity::factory()->create(['name' => 'Vivo X Fold6', 'slug' => 'vivo x fold6']);

    $this->artisan('entities:fix-spaced-slugs --dry-run')->assertSuccessful();

    expect($spaced->fresh()->slug)->toBe('vivo x fold6');
});
