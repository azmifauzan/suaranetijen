<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Enums\SourceType;
use App\Domains\Sources\Models\Source;
use App\Domains\Themes\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests still boot the app (no RefreshDatabase) so plain classes can read config(),
// e.g. ScoreCalculator reading config/scoring.php instead of hard-coded thresholds.
pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function themeModeFixture(): array
{
    $category = Category::create(['name' => 'Smartphone', 'slug' => 'smartphone', 'status' => CategoryStatus::Active]);
    $entity = Entity::create([
        'category_id' => $category->id, 'type' => EntityType::Brand, 'name' => 'Samsung', 'slug' => 'samsung',
        'status' => EntityStatus::Active, 'searchable' => true, 'rankable' => true,
    ]);
    $source = Source::create([
        'key' => 'dwh', 'name' => 'DWH', 'adapter' => 'DiskusiWebHostingAdapter',
        'source_type' => SourceType::Forum, 'enabled' => true, 'priority' => 10,
        'health_state' => SourceHealthState::Healthy,
    ]);
    $keyword = Theme::create(['slug' => 'murah', 'display_label' => 'Murah', 'canonical_key' => 'price_affordable']);
    $llm = Theme::create(['slug' => 'baterai-cepat-habis', 'display_label' => 'Baterai cepat habis', 'canonical_key' => 'baterai-cepat-habis']);

    return [$entity, $source, $keyword, $llm];
}
