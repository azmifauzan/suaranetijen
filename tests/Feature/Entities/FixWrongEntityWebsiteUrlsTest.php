<?php

use App\Domains\Entities\Models\Entity;

it('replaces or clears wrong website URLs but leaves an edited one alone', function () {
    $replaced = Entity::factory()->create(['slug' => 'dana', 'website_url' => 'https://www.danainternational.co.il']);
    $cleared = Entity::factory()->create(['slug' => 'club', 'website_url' => 'https://www.atleticodemadrid.com']);
    $edited = Entity::factory()->create(['slug' => 'jago', 'website_url' => 'https://jago.com/manual']);
    $right = Entity::factory()->create(['slug' => 'samsung', 'website_url' => 'https://www.samsung.com']);

    (require database_path('migrations/2026_10_06_025016_fix_wrong_entity_website_urls.php'))->up();

    expect($replaced->fresh()->website_url)->toBe('https://www.dana.id')
        ->and($cleared->fresh()->website_url)->toBeNull()
        ->and($edited->fresh()->website_url)->toBe('https://jago.com/manual')
        ->and($right->fresh()->website_url)->toBe('https://www.samsung.com');
});
