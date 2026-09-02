<?php

namespace Database\Factories;

use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Models\SourceItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceItem>
 */
class SourceItemFactory extends Factory
{
    protected $model = SourceItem::class;

    public function definition(): array
    {
        $text = fake()->paragraph();

        return [
            'source_id' => Source::factory(),
            'source_document_id' => SourceDocument::factory(),
            'external_id' => fake()->unique()->numerify('item-#####'),
            'raw_payload_ref' => 'payload-'.fake()->uuid(),
            'content_hash' => hash('sha256', $text),
            'processing_state' => ProcessingState::Pending,
            'published_at' => CarbonImmutable::now()->subDays(fake()->numberBetween(1, 10)),
            'expires_at' => CarbonImmutable::now()->addDays(3),
        ];
    }
}
