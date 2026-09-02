<?php

namespace App\Domains\Search\Models;

use App\Domains\Entities\Services\TextNormalizer;
use App\Models\User;
use Database\Factories\SearchQueryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $query
 * @property string $normalized_query
 * @property int $result_count
 * @property int|null $user_id
 * @property string|null $session_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['query', 'normalized_query', 'result_count', 'user_id', 'session_id'])]
class SearchQuery extends Model
{
    /** @use HasFactory<SearchQueryFactory> */
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (self $searchQuery): void {
            if (empty($searchQuery->normalized_query)) {
                $searchQuery->normalized_query = TextNormalizer::normalize($searchQuery->query);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result_count' => 'integer',
        ];
    }

    /**
     * Get the user who executed the search, if authenticated.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
