<?php

namespace App\Domains\Entities\Jobs;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\OfficialWebsiteFinder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class EnrichEntityWebsiteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(
        public int $entityId
    ) {
        $this->queue = 'maintenance';
    }

    public function handle(OfficialWebsiteFinder $finder): void
    {
        /** @var Entity|null $entity */
        $entity = Entity::query()->find($this->entityId);
        if ($entity === null) {
            return;
        }

        if ($entity->website_url !== null) {
            Cache::put("enrich:website:{$this->entityId}", true, now()->addDays(30));

            return;
        }

        $url = $finder->find($entity);
        if ($url !== null) {
            if (blank($entity->fresh()?->website_url)) {
                $entity->update(['website_url' => $url]);
            }
            Cache::put("enrich:website:{$this->entityId}", true, now()->addDays(30));
        }
    }
}
