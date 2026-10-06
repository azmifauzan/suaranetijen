<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;

it('deletes aliases whose normalized value was corrupted and keeps real ones', function () {
    $dana = Entity::factory()->create(['slug' => 'dana']);
    $bad = EntityAlias::factory()->create(['entity_id' => $dana->id, 'alias' => 'Megawati Soekarnoputri', 'normalized_alias' => '-egawati-oekarnoputri']);
    $good = EntityAlias::factory()->create(['entity_id' => $dana->id, 'alias' => 'Dana Indonesia', 'normalized_alias' => 'dana indonesia']);

    (require database_path('migrations/2026_10_06_025302_delete_corrupted_entity_aliases.php'))->up();

    expect(EntityAlias::query()->whereKey($bad->id)->exists())->toBeFalse()
        ->and(EntityAlias::query()->whereKey($good->id)->exists())->toBeTrue();
});
