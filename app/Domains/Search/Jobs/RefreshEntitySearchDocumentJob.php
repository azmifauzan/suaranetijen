<?php

namespace App\Domains\Search\Jobs;

use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Services\EntitySearchDocumentBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshEntitySearchDocumentJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return (string) $this->entityId;
    }

    public function __construct(
        public int $entityId
    ) {
        $this->onQueue('aggregate');
    }

    public function handle(EntitySearchDocumentBuilder $builder): void
    {
        $entity = Entity::find($this->entityId);
        if (! $entity) {
            return;
        }

        $builder->buildForEntity($entity);
    }
}
