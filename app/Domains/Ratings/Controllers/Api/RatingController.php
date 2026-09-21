<?php

namespace App\Domains\Ratings\Controllers\Api;

use App\Domains\Entities\Models\Entity;
use App\Domains\Ratings\Models\RatingSnapshot;
use App\Domains\Ratings\Models\UserRating;
use App\Domains\Ratings\Requests\StoreRatingRequest;
use App\Domains\Ratings\Services\RatingAggregator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RatingController extends Controller
{
    /**
     * Create or replace the authenticated user's rating for an entity.
     */
    public function update(StoreRatingRequest $request, Entity $entity, RatingAggregator $aggregator): JsonResponse
    {
        abort_unless($entity->status->isActive(), 404);

        $userId = (int) $request->user()->getAuthIdentifier();
        $rating = UserRating::updateOrCreate(
            ['user_id' => $userId, 'entity_id' => $entity->id],
            [
                'rating' => $request->integer('rating'),
                'review' => $request->filled('review') ? trim((string) $request->input('review')) : null,
            ]
        );
        $snapshot = $aggregator->refresh($entity->id);

        Log::info('rating.updated', [
            'user_id' => $userId,
            'entity_id' => $entity->id,
            'rating' => $rating->rating,
            'has_review' => ! empty($rating->review),
        ]);

        return response()->json(['data' => $this->payload($rating, $snapshot)]);
    }

    /**
     * Remove the authenticated user's rating for an entity.
     */
    public function destroy(Request $request, Entity $entity, RatingAggregator $aggregator): JsonResponse
    {
        abort_unless($entity->status->isActive(), 404);

        $userId = (int) $request->user()->getAuthIdentifier();
        UserRating::query()
            ->where('user_id', $userId)
            ->whereBelongsTo($entity)
            ->delete();

        $snapshot = $aggregator->refresh($entity->id);

        Log::info('rating.deleted', [
            'user_id' => $userId,
            'entity_id' => $entity->id,
        ]);

        return response()->json(['data' => $this->payload(null, $snapshot)]);
    }

    /**
     * @return array{rating: int|null, review: string|null, rating_count: int, rating_average: float|null}
     */
    private function payload(?UserRating $rating, RatingSnapshot $snapshot): array
    {
        return [
            'rating' => $rating?->rating,
            'review' => $rating?->review,
            'rating_count' => (int) $snapshot->rating_count,
            'rating_average' => $snapshot->rating_average === null
                ? null
                : (float) $snapshot->rating_average,
        ];
    }
}
