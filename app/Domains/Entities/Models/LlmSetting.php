<?php

namespace App\Domains\Entities\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Single shared row: every LLM-backed feature resolves its client through
 * this settings row (see LlmClientFactory) rather than hardcoding its own
 * base URL/model/key.
 *
 * @property int $id
 * @property string|null $base_url
 * @property string|null $model
 * @property string|null $api_key
 * @property int $max_tokens
 * @property float $temperature
 * @property int $timeout_seconds
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['base_url', 'model', 'api_key', 'max_tokens', 'temperature', 'timeout_seconds', 'updated_by'])]
class LlmSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'max_tokens' => 'integer',
            'temperature' => 'float',
            'timeout_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
