<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Models\SmartphoneSpec;
use App\Domains\Search\Jobs\RefreshEntitySearchDocumentJob;
use App\Domains\Search\Models\EntitySearchDocument;
use App\Domains\Search\Services\EntitySearchDocumentBuilder;
use Illuminate\Support\Facades\Queue;

it('updates search document idempotently when RefreshEntitySearchDocumentJob runs', function () {
    $entity = Entity::factory()->create([
        'name' => 'Test Mobile',
        'description' => 'Versi awal deskripsi ponsel.',
    ]);

    $job = new RefreshEntitySearchDocumentJob($entity->id);
    $builder = app(EntitySearchDocumentBuilder::class);

    // Run first time
    $job->handle($builder);
    $doc1 = EntitySearchDocument::where('entity_id', $entity->id)->first();
    expect($doc1)->not->toBeNull();
    expect($doc1->description_text)->toContain('versi awal deskripsi ponsel');

    // Run second time (idempotent)
    $job->handle($builder);
    $count = EntitySearchDocument::where('entity_id', $entity->id)->count();
    expect($count)->toBe(1);
});

it('dispatches RefreshEntitySearchDocumentJob when entity, alias, or spec is saved', function () {
    Queue::fake([RefreshEntitySearchDocumentJob::class]);

    $entity = Entity::factory()->create(['description' => 'Original desc']);
    Queue::assertPushed(RefreshEntitySearchDocumentJob::class, fn ($job) => $job->entityId === $entity->id);

    // Alias saved
    $alias = EntityAlias::create([
        'entity_id' => $entity->id,
        'alias' => 'Test Alias',
    ]);
    Queue::assertPushed(RefreshEntitySearchDocumentJob::class, fn ($job) => $job->entityId === $entity->id);

    // Spec saved
    SmartphoneSpec::create([
        'entity_id' => $entity->id,
        'chipset' => 'Dimensity 9300',
    ]);
    Queue::assertPushed(RefreshEntitySearchDocumentJob::class, fn ($job) => $job->entityId === $entity->id);
});

it('rebuilds all documents via search:rebuild-documents command', function () {
    $e1 = Entity::factory()->create(['name' => 'Entity 1', 'description' => 'Desc 1', 'searchable' => true]);
    $e2 = Entity::factory()->create(['name' => 'Entity 2', 'description' => 'Desc 2', 'searchable' => true]);
    $disabled = Entity::factory()->disabled()->create(['name' => 'Disabled Entity']);

    // Clear existing documents
    EntitySearchDocument::query()->delete();

    $this->artisan('search:rebuild-documents', ['--chunk' => 10])
        ->assertSuccessful();

    expect(EntitySearchDocument::where('entity_id', $e1->id)->exists())->toBeTrue();
    expect(EntitySearchDocument::where('entity_id', $e2->id)->exists())->toBeTrue();
    expect(EntitySearchDocument::where('entity_id', $disabled->id)->exists())->toBeFalse();
});

it('only refreshes the search document when something it is built from changed', function () {
    // Separate entities: the job is unique per entity, so a second push for the same one is deduplicated.
    $renamed = Entity::factory()->create(['description' => 'awal']);
    $described = Entity::factory()->create(['description' => 'awal']);
    $disabled = Entity::factory()->create(['description' => 'awal']);
    Queue::fake([RefreshEntitySearchDocumentJob::class]);

    $renamed->update(['name' => 'Nama Baru']);
    Queue::assertNothingPushed();

    $described->update(['description' => 'deskripsi baru']);
    Queue::assertPushed(RefreshEntitySearchDocumentJob::class, fn ($job) => $job->entityId === $described->id);

    $disabled->update(['status' => EntityStatus::Disabled]);
    Queue::assertPushed(RefreshEntitySearchDocumentJob::class, fn ($job) => $job->entityId === $disabled->id);
    Queue::assertNotPushed(RefreshEntitySearchDocumentJob::class, fn ($job) => $job->entityId === $renamed->id);
});
