<?php

namespace App\Domains\Sources\Models;

use App\Domains\Sources\Enums\DocumentState;
use Carbon\CarbonImmutable;
use Database\Factories\SourceDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $source_id
 * @property string $external_id
 * @property string|null $canonical_url
 * @property string|null $title
 * @property string|null $title_hash
 * @property string|null $content_hash
 * @property DocumentState $state
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable $discovered_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Source $source
 */
#[Fillable([
    'source_id',
    'external_id',
    'canonical_url',
    'title',
    'title_hash',
    'content_hash',
    'state',
    'published_at',
    'discovered_at',
    'last_seen_at',
])]
class SourceDocument extends Model
{
    /** @use HasFactory<SourceDocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => DocumentState::class,
            'published_at' => 'immutable_datetime',
            'discovered_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return HasMany<SourceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SourceItem::class);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SourceDocumentFactory
    {
        return SourceDocumentFactory::new();
    }
}
