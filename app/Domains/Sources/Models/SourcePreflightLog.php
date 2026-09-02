<?php

namespace App\Domains\Sources\Models;

use App\Domains\Sources\Enums\SourceHealthState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $source_id
 * @property SourceHealthState $status
 * @property int|null $response_time_ms
 * @property string|null $message
 * @property array<string, mixed>|null $details
 * @property CarbonImmutable $created_at
 * @property-read Source $source
 */
#[Fillable([
    'source_id',
    'status',
    'response_time_ms',
    'message',
    'details',
])]
class SourcePreflightLog extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SourceHealthState::class,
            'response_time_ms' => 'integer',
            'details' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
