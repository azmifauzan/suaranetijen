<?php

namespace App\Domains\Entities\Commands;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Models\SentimentObservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FixSpacedEntitySlugsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'entities:fix-spaced-slugs {--dry-run : Report what would change without writing anything}';

    /**
     * @var string
     */
    protected $description = 'Give entities with a non-URL slug (containing spaces) a proper slug; drop the ones that duplicate an existing entity by name';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $renamed = 0;
        $dropped = 0;
        $kept = 0;

        Entity::query()->where('slug', 'like', '% %')->orderBy('id')->chunkById(500, function ($entities) use ($dryRun, &$renamed, &$dropped, &$kept): void {
            foreach ($entities as $entity) {
                $twin = Entity::query()
                    ->whereRaw('lower(name) = ?', [mb_strtolower($entity->name)])
                    ->where('slug', 'not like', '% %')
                    ->whereKeyNot($entity->id)
                    ->first();

                if ($twin !== null) {
                    if (SentimentObservation::query()->where('entity_id', $entity->id)->exists()) {
                        $this->warn("{$entity->name} (#{$entity->id}) duplicates #{$twin->id} but has opinions; merge it by hand.");
                        $kept++;

                        continue;
                    }

                    if (! $dryRun) {
                        DB::transaction(function () use ($entity, $twin): void {
                            DB::table('entity_candidates')->where('entity_id', $entity->id)->update(['entity_id' => $twin->id]);
                            $entity->delete();
                        });
                    }
                    $dropped++;

                    continue;
                }

                if (! $dryRun) {
                    $entity->forceFill(['slug' => $this->uniqueSlug($entity)])->save();
                }
                $renamed++;
            }
        });

        $verb = $dryRun ? 'Would' : 'Did';
        $this->info("{$verb}: rename {$renamed}, drop {$dropped} duplicate(s); {$kept} left for manual merge.");

        return self::SUCCESS;
    }

    private function uniqueSlug(Entity $entity): string
    {
        $base = Str::slug($entity->name) ?: 'entitas';
        $slug = $base;

        for ($suffix = 2; Entity::query()->where('slug', $slug)->whereKeyNot($entity->id)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
