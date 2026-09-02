<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Entities\Services\EntityMatcher;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Models\UnmatchedMention;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MatchEntitiesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $sourceItemId
    ) {
        $this->queue = 'analysis';
    }

    public function handle(EntityMatcher $matcher): void
    {
        $item = SourceItem::find($this->sourceItemId);
        if ($item === null) {
            return;
        }

        $payload = RawPayload::query()
            ->where('source_item_id', $item->id)
            ->latest('id')
            ->value('payload');

        if (! is_string($payload) || $payload === '') {
            $item->update(['processing_state' => ProcessingState::Failed]);

            return;
        }

        $entity = $matcher->match($payload);
        if ($entity === null) {
            UnmatchedMention::updateOrCreate(
                ['source_item_id' => $item->id],
                [
                    'source_id' => $item->source_id,
                    'content_hash' => $item->content_hash,
                    'reason' => 'entity_not_resolved',
                ]
            );
            $item->update(['processing_state' => ProcessingState::Skipped]);

            return;
        }

        UnmatchedMention::query()->where('source_item_id', $item->id)->delete();
        ClassifySentimentJob::dispatch($item->id, $entity->id);
    }
}
