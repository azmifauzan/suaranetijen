<?php

namespace App\Domains\Sources\Models;

use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Enums\SourceType;
use Carbon\CarbonImmutable;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $adapter
 * @property SourceType $source_type
 * @property bool $enabled
 * @property int $priority
 * @property array<string, mixed>|null $crawl_policy
 * @property array<string, mixed>|null $retention_policy
 * @property SourceHealthState $health_state
 * @property CarbonImmutable|null $last_preflight_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'key',
    'name',
    'adapter',
    'source_type',
    'enabled',
    'priority',
    'crawl_policy',
    'retention_policy',
    'health_state',
    'last_preflight_at',
])]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'health_state' => SourceHealthState::class,
            'enabled' => 'boolean',
            'priority' => 'integer',
            'crawl_policy' => 'array',
            'retention_policy' => 'array',
            'last_preflight_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<SourceDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SourceDocument::class);
    }

    /**
     * @return HasMany<SourceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SourceItem::class);
    }

    /**
     * @return HasMany<CrawlState, $this>
     */
    public function crawlStates(): HasMany
    {
        return $this->hasMany(CrawlState::class);
    }

    /**
     * @return HasMany<SourcePreflightLog, $this>
     */
    public function preflightLogs(): HasMany
    {
        return $this->hasMany(SourcePreflightLog::class);
    }

    /**
     * @return HasMany<RawPayload, $this>
     */
    public function rawPayloads(): HasMany
    {
        return $this->hasMany(RawPayload::class);
    }

    /**
     * @return HasMany<UnmatchedMention, $this>
     */
    public function unmatchedMentions(): HasMany
    {
        return $this->hasMany(UnmatchedMention::class);
    }

    /**
     * Scope query to enabled sources.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope query to operational sources (enabled and healthy/degraded).
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('enabled', true)
            ->whereIn('health_state', [
                SourceHealthState::Healthy->value,
                SourceHealthState::Degraded->value,
            ]);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SourceFactory
    {
        return SourceFactory::new();
    }
}
