<?php

namespace App\Domains\Ratings\Services;

use App\Domains\Ratings\Models\RatingSnapshot;
use App\Domains\Ratings\Models\UserRating;

class RatingAggregator
{
    /**
     * Recompute and persist the first-party rating snapshot for an entity.
     */
    public function refresh(int $entityId): RatingSnapshot
    {
        $ratings = UserRating::query()->where('entity_id', $entityId);

        $ratingCount = $ratings->count();
        $ratingAverage = $ratings->avg('rating');
        $ratingAverage = $ratingAverage === null
            ? null
            : round((float) $ratingAverage, 2);

        return RatingSnapshot::updateOrCreate(
            ['entity_id' => $entityId],
            [
                'rating_count' => $ratingCount,
                'rating_average' => $ratingAverage,
                'calculated_at' => now(),
            ]
        );
    }
}
