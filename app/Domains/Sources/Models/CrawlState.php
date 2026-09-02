<?php

namespace App\Domains\Sources\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CrawlStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $source_id
 * @property string $cursor_key
 * @property string|null $cursor_value
 * @property string|null $last_external_id
 * @property CarbonImmutable|null $last_crawled_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Source $source
 */
#[Fillable([
    'source_id',
    'cursor_key',
    'cursor_value',
    'last_external_id',
    'last_crawled_at',
    'metadata',
])]
class CrawlState extends Model
{
    /** @use HasFactory<CrawlStateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_crawled_at' => 'immutable_datetime',
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
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): CrawlStateFactory
    {
        return CrawlStateFactory::new();
    }
}
