<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Category;
use App\Domains\Search\Enums\SearchLandingPageSource;
use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Search\Models\SearchLandingPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SearchLandingPage>
 */
class SearchLandingPageFactory extends Factory
{
    protected $model = SearchLandingPage::class;

    public function definition(): array
    {
        $keyword = fake()->unique()->word().' '.fake()->word();

        return [
            'slug' => Str::slug($keyword),
            'keyword' => $keyword,
            'normalized_keyword' => Str::lower($keyword),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(4),
            'meta_description' => fake()->sentence(10),
            'intro' => fake()->paragraph(),
            'status' => SearchLandingPageStatus::Candidate,
            'source' => SearchLandingPageSource::SearchQuery,
            'candidate_signal' => fake()->numberBetween(1, 20),
            'llm_drafted_at' => null,
            'published_at' => null,
        ];
    }

    public function candidate(): static
    {
        return $this->state(fn () => [
            'status' => SearchLandingPageStatus::Candidate,
            'llm_drafted_at' => null,
            'published_at' => null,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => SearchLandingPageStatus::Draft,
            'llm_drafted_at' => now(),
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => SearchLandingPageStatus::Published,
            'llm_drafted_at' => now(),
            'published_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => SearchLandingPageStatus::Rejected,
            'published_at' => null,
        ]);
    }

    public function searchQuery(): static
    {
        return $this->state(fn () => [
            'source' => SearchLandingPageSource::SearchQuery,
        ]);
    }

    public function categoryTheme(): static
    {
        return $this->state(fn () => [
            'source' => SearchLandingPageSource::CategoryTheme,
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'source' => SearchLandingPageSource::Manual,
        ]);
    }
}
