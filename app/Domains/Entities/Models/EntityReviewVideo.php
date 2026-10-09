<?php

namespace App\Domains\Entities\Models;

use Database\Factories\EntityReviewVideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A YouTube review video shown on a product page. Only the video id and its
 * public title are kept, never the channel: the player itself credits the
 * channel, and the index rates entities, not authors.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $youtube_id
 * @property string $title
 * @property Carbon|null $published_at
 */
#[Fillable(['entity_id', 'youtube_id', 'title', 'published_at'])]
class EntityReviewVideo extends Model
{
    /** @use HasFactory<EntityReviewVideoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    protected static function newFactory(): EntityReviewVideoFactory
    {
        return EntityReviewVideoFactory::new();
    }
}
