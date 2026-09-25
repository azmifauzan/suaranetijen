<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Jobs\EnrichEntityWebsiteJob;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\OfficialWebsiteFinder;
use App\Domains\Ingestion\Jobs\ClassifySentimentJob;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\SentimentClassifier;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('finds official website from Wikidata for an entity', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*action=wbsearchentities*' => Http::response([
            'search' => [
                [
                    'id' => 'Q20718',
                    'label' => 'Samsung Electronics',
                    'description' => 'South Korean multinational electronics corporation',
                ],
            ],
        ]),
        '*action=wbgetclaims*entity=Q20718*' => Http::response([
            'claims' => [
                'P856' => [
                    [
                        'mainsnak' => [
                            'datavalue' => [
                                'value' => 'http://www.samsung.com/',
                            ],
                        ],
                        'rank' => 'normal',
                    ],
                ],
            ],
        ]),
    ]);

    $entity = Entity::factory()->create([
        'name' => 'Samsung',
        'type' => EntityType::Brand,
        'website_url' => null,
    ]);

    $finder = new OfficialWebsiteFinder;
    $url = $finder->find($entity);

    expect($url)->toBe('https://www.samsung.com');
});

it('prioritizes indonesian domains and preferred ranks over deprecated and foreign links', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*action=wbsearchentities*' => Http::response([
            'search' => [
                [
                    'id' => 'Q806626',
                    'label' => 'Bank Central Asia',
                    'description' => 'Indonesian bank',
                ],
            ],
        ]),
        '*action=wbgetclaims*entity=Q806626*' => Http::response([
            'claims' => [
                'P856' => [
                    [
                        'mainsnak' => [
                            'datavalue' => ['value' => 'http://old-deprecated.bca.co.id'],
                        ],
                        'rank' => 'deprecated',
                    ],
                    [
                        'mainsnak' => [
                            'datavalue' => ['value' => 'https://klikbca.com/'],
                        ],
                        'rank' => 'normal',
                    ],
                    [
                        'mainsnak' => [
                            'datavalue' => ['value' => 'https://www.bca.co.id/'],
                        ],
                        'rank' => 'preferred',
                    ],
                ],
            ],
        ]),
    ]);

    $entity = Entity::factory()->create([
        'name' => 'BCA',
        'type' => EntityType::Brand,
    ]);

    $finder = new OfficialWebsiteFinder;
    $url = $finder->find($entity);

    expect($url)->toBe('https://www.bca.co.id');
});

it('ignores irrelevant geographic candidates for brands and products', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*action=wbsearchentities*' => Http::response([
            'search' => [
                [
                    'id' => 'Q12345',
                    'label' => 'Semarang',
                    'description' => 'village in Indonesia',
                ],
                [
                    'id' => 'Q67890',
                    'label' => 'Semarang Brand',
                    'description' => 'Indonesian manufacturer company',
                ],
            ],
        ]),
        '*action=wbgetclaims*entity=Q67890*' => Http::response([
            'claims' => [
                'P856' => [
                    [
                        'mainsnak' => [
                            'datavalue' => ['value' => 'https://semarang-brand.co.id/'],
                        ],
                        'rank' => 'normal',
                    ],
                ],
            ],
        ]),
    ]);

    $entity = Entity::factory()->create([
        'name' => 'Semarang Brand',
        'type' => EntityType::Brand,
    ]);

    $finder = new OfficialWebsiteFinder;
    $url = $finder->find($entity);

    expect($url)->toBe('https://semarang-brand.co.id');
});

it('searches with parent name when entity is a child product', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'Toyota+Avanza') || str_contains($request->url(), 'Toyota%20Avanza')) {
            return Http::response([
                'search' => [
                    [
                        'id' => 'Q1820293',
                        'label' => 'Toyota Avanza',
                        'description' => 'MPV sold by Toyota',
                    ],
                ],
            ]);
        }

        if (str_contains($request->url(), 'entity=Q1820293')) {
            return Http::response([
                'claims' => [
                    'P856' => [
                        [
                            'mainsnak' => [
                                'datavalue' => ['value' => 'https://www.toyota.astra.co.id/product/avanza'],
                            ],
                            'rank' => 'normal',
                        ],
                    ],
                ],
            ]);
        }

        return Http::response(['search' => []]);
    });

    $parent = Entity::factory()->create(['name' => 'Toyota', 'type' => EntityType::Brand]);
    $child = Entity::factory()->create([
        'name' => 'Avanza',
        'type' => EntityType::Product,
        'parent_id' => $parent->id,
    ]);

    $finder = new OfficialWebsiteFinder;
    $url = $finder->find($child);

    expect($url)->toBe('https://www.toyota.astra.co.id/product/avanza');
});

it('enriches entity website_url in background EnrichEntityWebsiteJob', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*action=wbsearchentities*' => Http::response([
            'search' => [
                ['id' => 'Q100', 'description' => 'company'],
            ],
        ]),
        '*action=wbgetclaims*entity=Q100*' => Http::response([
            'claims' => [
                'P856' => [
                    [
                        'mainsnak' => ['datavalue' => ['value' => 'https://telkomsel.com/']],
                        'rank' => 'normal',
                    ],
                ],
            ],
        ]),
    ]);

    $entity = Entity::factory()->create([
        'name' => 'Telkomsel',
        'type' => EntityType::Brand,
        'website_url' => null,
    ]);

    (new EnrichEntityWebsiteJob($entity->id))->handle(new OfficialWebsiteFinder);

    expect($entity->fresh()->website_url)->toBe('https://telkomsel.com');
});

it('does not overwrite existing website_url in EnrichEntityWebsiteJob', function () {
    Http::preventStrayRequests();
    Http::fake();

    $entity = Entity::factory()->create([
        'name' => 'Existing Brand',
        'website_url' => 'https://original.example.com',
    ]);

    (new EnrichEntityWebsiteJob($entity->id))->handle(new OfficialWebsiteFinder);

    expect($entity->fresh()->website_url)->toBe('https://original.example.com');
    Http::assertNothingSent();
});

it('runs batch command entities:enrich-websites to enrich top entities', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*action=wbsearchentities*search=Samsung*' => Http::response([
            'search' => [['id' => 'Q20718', 'description' => 'electronics company']],
        ]),
        '*action=wbgetclaims*entity=Q20718*' => Http::response([
            'claims' => [
                'P856' => [
                    ['mainsnak' => ['datavalue' => ['value' => 'https://samsung.com/id/']], 'rank' => 'preferred'],
                ],
            ],
        ]),
        '*action=wbsearchentities*search=Xiaomi*' => Http::response([
            'search' => [['id' => 'Q89758', 'description' => 'technology company']],
        ]),
        '*action=wbgetclaims*entity=Q89758*' => Http::response([
            'claims' => [
                'P856' => [
                    ['mainsnak' => ['datavalue' => ['value' => 'https://mi.co.id/']], 'rank' => 'preferred'],
                ],
            ],
        ]),
    ]);

    $samsung = Entity::factory()->create(['name' => 'Samsung', 'website_url' => null, 'status' => EntityStatus::Active]);
    $xiaomi = Entity::factory()->create(['name' => 'Xiaomi', 'website_url' => null, 'status' => EntityStatus::Active]);

    // Give Samsung higher opinion count in sentiment snapshot
    SentimentSnapshot::factory()->create([
        'entity_id' => $samsung->id,
        'period' => 'all',
        'opinion_count' => 150,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $xiaomi->id,
        'period' => 'all',
        'opinion_count' => 80,
    ]);

    $this->artisan('entities:enrich-websites', ['--limit' => 10, '--delay' => 0])
        ->assertSuccessful();

    expect($samsung->fresh()->website_url)->toBe('https://samsung.com/id')
        ->and($xiaomi->fresh()->website_url)->toBe('https://mi.co.id');
});

it('throttles website enrichment job dispatch from sentiment classification using cache lock', function () {
    Queue::fake();
    Cache::flush();

    $entity = Entity::factory()->create();
    $source = Source::factory()->create();

    $item1 = SourceItem::factory()->create([
        'source_id' => $source->id,
        'content_hash' => hash('sha256', 'Komentar pertama'),
    ]);
    app(RawPayloadStorage::class)->store($source, 'Komentar pertama sangat mantap', $item1, 'text/plain');

    $item2 = SourceItem::factory()->create([
        'source_id' => $source->id,
        'content_hash' => hash('sha256', 'Komentar kedua'),
    ]);
    app(RawPayloadStorage::class)->store($source, 'Komentar kedua juga bagus sekali', $item2, 'text/plain');

    $classifier = app(SentimentClassifier::class);

    // First opinion classifies and dispatches job
    (new ClassifySentimentJob($item1->id, $entity->id))->handle($classifier);
    Queue::assertPushed(EnrichEntityWebsiteJob::class, 1);

    // Second opinion within 7 days does NOT dispatch another EnrichEntityWebsiteJob
    (new ClassifySentimentJob($item2->id, $entity->id))->handle($classifier);
    Queue::assertPushed(EnrichEntityWebsiteJob::class, 1);
});

it('skips municipal city candidates and government domains for automotive brand Toyota', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*action=wbsearchentities*' => Http::response([
            'search' => [
                [
                    'id' => 'Q201117',
                    'label' => 'Toyota',
                    'description' => 'city in Aichi Prefecture, Japan',
                ],
                [
                    'id' => 'Q53268',
                    'label' => 'Toyota',
                    'description' => 'Japanese multinational automotive manufacturer',
                ],
            ],
        ]),
        '*action=wbgetclaims*entity=Q53268*' => Http::response([
            'claims' => [
                'P856' => [
                    [
                        'mainsnak' => [
                            'datavalue' => ['value' => 'https://www.toyota.com/'],
                        ],
                        'rank' => 'preferred',
                    ],
                ],
            ],
        ]),
    ]);

    $entity = Entity::factory()->create([
        'name' => 'Toyota',
        'type' => EntityType::Brand,
    ]);

    $finder = new OfficialWebsiteFinder;
    $url = $finder->find($entity);

    expect($url)->toBe('https://www.toyota.com');
});

it('allows github.com official website for GitHub entity', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*action=wbsearchentities*' => Http::response([
            'search' => [
                [
                    'id' => 'Q364',
                    'label' => 'GitHub',
                    'description' => 'hosting service for software projects using Git',
                ],
            ],
        ]),
        '*action=wbgetclaims*entity=Q364*' => Http::response([
            'claims' => [
                'P856' => [
                    [
                        'mainsnak' => [
                            'datavalue' => ['value' => 'https://github.com'],
                        ],
                        'rank' => 'normal',
                    ],
                ],
            ],
        ]),
    ]);

    $entity = Entity::factory()->create([
        'name' => 'GitHub',
        'type' => EntityType::Service,
    ]);

    $finder = new OfficialWebsiteFinder;
    $url = $finder->find($entity);

    expect($url)->toBe('https://github.com');
});
