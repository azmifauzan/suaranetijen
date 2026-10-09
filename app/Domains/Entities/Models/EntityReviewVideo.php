<?php

namespace App\Domains\Entities\Models;

use Database\Factories\EntityReviewVideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string $source auto or manual
 * @property Carbon|null $published_at
 * @property Carbon|null $hidden_at
 */
#[Fillable(['entity_id', 'youtube_id', 'title', 'source', 'published_at', 'hidden_at'])]
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
            'hidden_at' => 'immutable_datetime',
        ];
    }

    public const SOURCE_AUTO = 'auto';

    public const SOURCE_MANUAL = 'manual';

    /**
     * Videos visible on the public product page.
     *
     * @param  Builder<EntityReviewVideo>  $query
     * @return Builder<EntityReviewVideo>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
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
