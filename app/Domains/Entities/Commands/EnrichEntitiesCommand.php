<?php

namespace App\Domains\Entities\Commands;

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\OfficialWebsiteFinder;
use App\Domains\Entities\Services\WikidataDetailFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class EnrichEntitiesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'entities:enrich
                            {--limit=50 : Maximum number of entities to look up on Wikidata}
                            {--delay=200000 : Delay between entities in microseconds}';

    /**
     * @var string
     */
    protected $description = 'Fill website, image and reference details from Wikidata, then fetch review videos';

    /**
     * Attributes a detail relation accepts, so a Wikidata value never lands on a column the category lacks.
     *
     * @var array<string, list<string>>
     */
    private const DETAIL_COLUMNS = [
        'personProfile' => ['birth_date', 'birth_place', 'occupation', 'affiliation'],
        'smartphoneSpec' => ['release_year', 'os', 'chipset'],
        'carSpec' => ['release_year'],
        'motorcycleSpec' => ['release_year'],
    ];

    public function handle(OfficialWebsiteFinder $websites, WikidataDetailFinder $details): int
    {
        $entities = Entity::query()
            ->active()
            ->with(['category', 'aliases', 'parent'])
            ->leftJoin('sentiment_snapshots', fn ($join) => $join->on('entities.id', '=', 'sentiment_snapshots.entity_id')->where('sentiment_snapshots.period', 'all'))
            ->select('entities.*')
            ->orderByRaw('COALESCE(sentiment_snapshots.opinion_count, 0) DESC')
            ->orderBy('entities.id')
            ->get()
            ->reject(fn (Entity $entity): bool => Cache::has("enrich:wikidata:{$entity->id}"))
            ->take(max(1, (int) $this->option('limit')));

        $found = 0;
        foreach ($entities as $entity) {
            $website = blank($entity->website_url) ? $websites->find($entity) : null;
            $result = $details->find($entity);

            if ($website !== null) {
                $entity->website_url = $website;
            }
            if ($result !== null && blank($entity->image_url)) {
                $entity->image_url = $result['image_url'];
                $entity->image_credit = $result['image_credit'];
            }
            $entity->save();

            if ($result !== null) {
                $this->fillDetails($entity, $result['details']);
            }

            $hit = $website !== null || $result !== null;
            $found += $hit ? 1 : 0;
            Cache::put("enrich:wikidata:{$entity->id}", true, now()->addDays($hit ? 30 : 7));
            usleep(max(0, (int) $this->option('delay')));
        }

        $this->info("Enriched {$found} of {$entities->count()} entities.");

        return $this->call('entities:fetch-review-videos');
    }

    /**
     * Only empty columns are filled, so a value an admin typed is never overwritten.
     *
     * @param  array<string, mixed>  $values
     */
    private function fillDetails(Entity $entity, array $values): void
    {
        $relation = match (true) {
            $entity->type === EntityType::Person => 'personProfile',
            $entity->type === EntityType::Product => match ($entity->category->slug) {
                'smartphone' => 'smartphoneSpec',
                'mobil' => 'carSpec',
                'motor' => 'motorcycleSpec',
                default => null,
            },
            default => null,
        };

        $values = $relation === null ? [] : array_intersect_key($values, array_flip(self::DETAIL_COLUMNS[$relation]));
        if ($values === []) {
            return;
        }

        $model = $entity->{$relation}()->firstOrNew([]);
        foreach ($values as $column => $value) {
            if (blank($model->getAttribute($column))) {
                $model->setAttribute($column, $value);
            }
        }
        $model->save();
    }
}
