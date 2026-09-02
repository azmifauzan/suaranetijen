<?php

namespace Database\Factories;

use App\Domains\Sources\Enums\DocumentState;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceDocument>
 */
class SourceDocumentFactory extends Factory
{
    protected $model = SourceDocument::class;

    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'source_id' => Source::factory(),
            'external_id' => fake()->unique()->numerify('doc-#####'),
            'canonical_url' => fake()->url(),
            'title' => $title,
            'title_hash' => hash('sha256', $title),
            'content_hash' => hash('sha256', fake()->paragraph()),
            'state' => DocumentState::Discovered,
            'published_at' => CarbonImmutable::now()->subDays(fake()->numberBetween(1, 30)),
            'discovered_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ];
    }
}
