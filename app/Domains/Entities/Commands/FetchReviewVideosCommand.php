<?php

namespace App\Domains\Entities\Commands;

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityReviewVideo;
use App\Domains\Entities\Services\EntityReviewVideoFinder;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchReviewVideosCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'entities:fetch-review-videos
                            {--limit= : Maximum number of products to search (each costs 100 YouTube quota units)}';

    /**
     * @var string
     */
    protected $description = 'Find YouTube review videos for products that have none yet, most discussed first';

    public function handle(EntityReviewVideoFinder $finder): int
    {
        if (trim((string) config('sources.youtube.api_key')) === '') {
            $this->warn('YOUTUBE_API_KEY is not set, skipping.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) ($this->option('limit') ?: config('sources.youtube.review_video_daily_limit', 20)));

        $entities = Entity::query()
            ->active()
            ->where('type', EntityType::Product)
            // Videos an admin added by hand do not stop the automatic search.
            ->whereDoesntHave('reviewVideos', fn ($query) => $query->where('source', EntityReviewVideo::SOURCE_AUTO))
            ->leftJoin('sentiment_snapshots', function ($join) {
                $join->on('entities.id', '=', 'sentiment_snapshots.entity_id')
                    ->where('sentiment_snapshots.period', '=', 'all');
            })
            ->select('entities.*')
            ->orderByRaw('COALESCE(sentiment_snapshots.opinion_count, 0) DESC')
            ->orderBy('entities.id')
            ->limit($limit * 3)
            ->get()
            ->reject(fn (Entity $entity): bool => Cache::has($this->checkedKey($entity)))
            ->take($limit);

        $found = 0;

        foreach ($entities as $entity) {
            try {
                $videos = $finder->find($entity);
            } catch (RequestException $e) {
                // Quota exhausted or key rejected: stop, and do not mark this product as checked.
                Log::warning('Review video search failed, stopping.', ['status' => $e->response->status()]);
                $this->error("YouTube search failed ({$e->response->status()}), stopping.");

                return self::FAILURE;
            }

            foreach ($videos as $video) {
                // firstOrCreate: never overwrite an admin's manual or hidden copy of the same video.
                EntityReviewVideo::query()->firstOrCreate(
                    ['entity_id' => $entity->id, 'youtube_id' => $video['youtube_id']],
                    ['title' => $video['title'], 'published_at' => $video['published_at']]
                );
            }

            Cache::put($this->checkedKey($entity), true, now()->addDays(30));
            $found += $videos === [] ? 0 : 1;
            $this->line(sprintf('  [%d] %s: %d video', $entity->id, $entity->name, count($videos)));
        }

        $this->info("Searched {$entities->count()} product(s), {$found} now have review videos.");

        return self::SUCCESS;
    }

    private function checkedKey(Entity $entity): string
    {
        return "review-videos:checked:{$entity->id}";
    }
}
