<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use Illuminate\Support\Facades\Log;

function concentrationObservations(Source $source, Entity $entity, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $item = SourceItem::factory()->create(['source_id' => $source->id]);
        SentimentObservation::factory()->create([
            'entity_id' => $entity->id,
            'source_id' => $source->id,
            'source_item_id' => $item->id,
            'created_at' => now(),
        ]);
    }
}

test('monitor flags a source where one entity takes most of the last 24 hours', function () {
    $source = Source::factory()->create(['name' => 'Forum Contoh']);
    $github = Entity::factory()->create(['name' => 'GitHub']);
    $other = Entity::factory()->create(['name' => 'Lainnya']);

    concentrationObservations($source, $github, 55);
    concentrationObservations($source, $other, 3);

    Log::spy();

    $this->artisan('monitor:metrics', ['--fail-on-breach' => true])
        ->expectsOutputToContain('Forum Contoh gave 95% of its last-24h opinions (55 of 58) to one entity (GitHub)')
        ->assertExitCode(1);
});

test('monitor does not flag a source with an even spread or too few opinions', function () {
    $source = Source::factory()->create(['name' => 'Forum Rata']);
    $entities = Entity::factory()->count(4)->create();

    foreach ($entities as $entity) {
        concentrationObservations($source, $entity, 15);
    }

    $thin = Source::factory()->create(['name' => 'Forum Sepi']);
    concentrationObservations($thin, $entities[0], 10);

    $this->artisan('monitor:metrics')
        ->doesntExpectOutputToContain('to one entity')
        ->assertExitCode(0);
});
