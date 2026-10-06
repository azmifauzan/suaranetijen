<?php

use App\Domains\Entities\Models\Entity;

it('fills empty descriptions without overwriting an edited one', function () {
    $empty = Entity::factory()->create(['slug' => 'github', 'description' => null]);
    $blank = Entity::factory()->create(['slug' => 'pertamina', 'description' => '']);
    $edited = Entity::factory()->create(['slug' => 'nissan', 'description' => 'Deskripsi hasil suntingan admin']);
    $unlisted = Entity::factory()->create(['slug' => 'tidak-ada-di-daftar', 'description' => null]);

    (require database_path('migrations/2026_10_06_021355_fill_missing_entity_descriptions.php'))->up();

    expect($empty->fresh()->description)->toBe('Platform hosting kode dan kolaborasi pengembang perangkat lunak')
        ->and($blank->fresh()->description)->toBe('Perusahaan energi milik negara Indonesia')
        ->and($edited->fresh()->description)->toBe('Deskripsi hasil suntingan admin')
        ->and($unlisted->fresh()->description)->toBeNull();
});

it('keeps the seed CSV in step with the migration for the entities it lists', function () {
    $rows = array_map('str_getcsv', file(database_path('data/seed_entities.csv'), FILE_IGNORE_NEW_LINES));
    $github = collect($rows)->first(fn (array $row) => $row[0] === 'GitHub');

    expect($github[5])->toBe('Platform hosting kode dan kolaborasi pengembang perangkat lunak');
});
