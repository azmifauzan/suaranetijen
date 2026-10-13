<?php

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\PersonProfile;
use Illuminate\Support\Facades\Http;

function fakeWikidata(string $label): void
{
    Http::preventStrayRequests();
    Http::fake([
        '*action=wbsearchentities*' => Http::response(['search' => [['id' => 'Q1', 'label' => $label, 'description' => 'Indonesian politician']]]),
        '*wikidata.org*ids=Q1*' => Http::response(['entities' => ['Q1' => ['claims' => [
            'P31' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q5']]]]],
            'P18' => [['mainsnak' => ['datavalue' => ['value' => 'Foto.jpg']]]],
            'P569' => [['mainsnak' => ['datavalue' => ['value' => ['time' => '+1961-06-21T00:00:00Z', 'precision' => 11]]]]],
            'P19' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q4803']]]]],
        ]]]]),
        '*wikidata.org*ids=Q4803*' => Http::response(['entities' => ['Q4803' => ['labels' => ['id' => ['value' => 'Surakarta']]]]]),
        '*commons.wikimedia.org*' => Http::response(['query' => ['pages' => ['9' => ['imageinfo' => [[
            'thumburl' => 'https://upload.wikimedia.org/800px-Foto.jpg',
            'extmetadata' => ['Artist' => ['value' => '<a href="#">Fotografer</a>'], 'LicenseShortName' => ['value' => 'CC BY 4.0']],
        ]]]]]]),
    ]);
}

it('fills image and person details from Wikidata without overwriting admin edits', function () {
    fakeWikidata('Joko Widodo');
    $entity = Entity::factory()->create(['type' => EntityType::Person, 'name' => 'Joko Widodo']);
    PersonProfile::factory()->create(['entity_id' => $entity->id, 'birth_place' => 'Diisi admin', 'birth_date' => null]);

    $this->artisan('entities:enrich', ['--delay' => 0])->assertSuccessful();

    $entity->refresh();
    expect($entity->image_url)->toBe('https://upload.wikimedia.org/800px-Foto.jpg')
        ->and($entity->image_credit)->toBe('Fotografer · CC BY 4.0 · Wikimedia Commons')
        ->and($entity->personProfile->birth_date->toDateString())->toBe('1961-06-21')
        ->and($entity->personProfile->birth_place)->toBe('Diisi admin');
});

it('ignores a Wikidata item whose label is not the entity name', function () {
    fakeWikidata('Joko Widodo Lain');
    $entity = Entity::factory()->create(['type' => EntityType::Person, 'name' => 'Joko Widodo']);

    $this->artisan('entities:enrich', ['--delay' => 0])->assertSuccessful();

    expect($entity->fresh()->image_url)->toBeNull();
});
