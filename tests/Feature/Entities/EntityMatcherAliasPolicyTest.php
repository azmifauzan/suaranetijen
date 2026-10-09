<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Services\EntityMatcher;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Ingestion\Jobs\MatchEntitiesJob;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

function entityWithAliases(string $name, array $aliases): Entity
{
    $entity = Entity::factory()->create(['name' => $name, 'slug' => str($name)->slug()->toString()]);
    foreach ($aliases as $alias) {
        EntityAlias::factory()->create([
            'entity_id' => $entity->id,
            'alias' => $alias,
            'normalized_alias' => TextNormalizer::normalize($alias),
        ]);
    }

    return $entity;
}

it('does not match a blocked slang alias such as "ga"', function () {
    entityWithAliases('Garuda Indonesia', ['Garuda', 'GA']);

    expect(app(EntityMatcher::class)->match('lotionnya ga lengket dan wangi enak'))->toBeNull()
        ->and(app(EntityMatcher::class)->match('lotion garuda kemasan besar'))->toBeNull();
});

it('still matches the entity by its full name and reports the matched term', function () {
    $garuda = entityWithAliases('Garuda Indonesia', ['Garuda', 'GA']);

    $match = app(EntityMatcher::class)->matchWithTerm('Pelayanan Garuda Indonesia kali ini bagus');

    expect($match['entity']->is($garuda))->toBeTrue()
        ->and($match['term'])->toBe('garuda indonesia');
});

it('requires short letter-only aliases to be written in capitals', function () {
    $bca = entityWithAliases('Bank Central Asia', ['BCA']);

    expect(app(EntityMatcher::class)->match('transfer lewat bca lancar'))->toBeNull()
        ->and(app(EntityMatcher::class)->match('transfer lewat BCA lancar')?->is($bca))->toBeTrue();
});

it('keeps matching a short entity name in any case', function () {
    $byd = entityWithAliases('BYD', []);

    expect(app(EntityMatcher::class)->match('mobil byd seal nyaman')?->is($byd))->toBeTrue();
});

it('stores the matched term on the sentiment observation', function () {
    entityWithAliases('IDCloudHost', []);
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    app(RawPayloadStorage::class)->store($source, 'IDCloudHost sangat stabil dan cepat', $item, 'text/plain');

    MatchEntitiesJob::dispatch($item->id);

    expect(SentimentObservation::query()->value('matched_term'))->toBe('idcloudhost');
});

it('rejects a blocked alias from the admin alias form', function () {
    $admin = User::factory()->admin()->create();
    $entity = entityWithAliases('Garuda Indonesia', []);

    $this->actingAs($admin)
        ->post("/admin/entities/{$entity->id}/aliases", ['alias' => 'GA'])
        ->assertSessionHasErrors('alias');

    expect($entity->aliases()->where('normalized_alias', 'ga')->exists())->toBeFalse();
});

it('does not attribute a model variant to the base model', function () {
    $s24 = entityWithAliases('Samsung Galaxy S24', ['S24']);

    expect(app(EntityMatcher::class)->match('Baru beli S24 FE, worth it?'))->toBeNull()
        ->and(app(EntityMatcher::class)->match('kamera s24 ultra juara'))->toBeNull()
        ->and(app(EntityMatcher::class)->match('kamera S24 bagus banget')?->is($s24))->toBeTrue();
});

it('matches the base model when it also appears on its own next to a variant', function () {
    $s24 = entityWithAliases('Samsung Galaxy S24', ['S24']);

    expect(app(EntityMatcher::class)->match('S24 FE atau S24 biasa?')?->is($s24))->toBeTrue();
});

it('lets an entity that owns the variant phrase win over the base model', function () {
    entityWithAliases('Samsung Galaxy S24', ['S24']);
    $fe = entityWithAliases('Samsung Galaxy S24 FE', ['S24 FE']);

    expect(app(EntityMatcher::class)->match('Baru beli S24 FE, worth it?')?->is($fe))->toBeTrue();
});

it('serves entities from the candidate cache within its ttl', function () {
    config(['entity_matching.candidates_cache_seconds' => 60]);
    Cache::forget('entity-matcher:candidates');
    $first = entityWithAliases('IDCloudHost', []);

    expect(app(EntityMatcher::class)->match('IDCloudHost stabil')?->is($first))->toBeTrue();

    entityWithAliases('Biznet Gio', []);

    expect(app(EntityMatcher::class)->match('Biznet Gio stabil'))->toBeNull();

    Cache::forget('entity-matcher:candidates');

    expect(app(EntityMatcher::class)->match('Biznet Gio stabil'))->not->toBeNull();
});

it('matches an everyday-word brand only with a supporting context word', function (string $name, string $offTopic, string $onTopic) {
    $entity = entityWithAliases($name, []);

    expect(app(EntityMatcher::class)->match($offTopic))->toBeNull()
        ->and(app(EntityMatcher::class)->match($onTopic)?->id)->toBe($entity->id);
})->with([
    'jago' => ['Jago', 'mobil ini jago banget nanjak, radiatornya kecil', 'Aplikasi Jago gampang buat transfer'],
    'vidio' => ['Vidio', 'vidio nya bagus banget, mobilnya keren', 'Langganan Vidio buat nonton liga lancar'],
    'flip' => ['Flip', 'kemasan flip top praktis, wanginya soft', 'Flip gratis biaya admin transfer antarbank'],
]);
