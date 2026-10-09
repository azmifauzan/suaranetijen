<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityReviewVideo;
use App\Domains\Entities\Requests\SaveReviewVideoRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Admin control over the review videos shown on a product page. Videos found
 * by entities:fetch-review-videos stay; an admin can add links of their own,
 * edit titles, and remove or restore any of them.
 */
class AdminEntityReviewVideoController extends Controller
{
    public function store(SaveReviewVideoRequest $request, Entity $entity): RedirectResponse
    {
        abort_if($entity->type !== EntityType::Product, 404);

        $title = $request->string('title')->trim()->value();

        // A link to a video the search already found (or an admin hid) becomes a manual one.
        EntityReviewVideo::query()->updateOrCreate(
            ['entity_id' => $entity->id, 'youtube_id' => (string) $request->verifiedVideoId()],
            [
                'title' => $title !== '' ? $title : (string) $request->verifiedTitle(),
                'source' => EntityReviewVideo::SOURCE_MANUAL,
                'hidden_at' => null,
            ]
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review video added.']);

        return redirect()->back();
    }

    public function update(SaveReviewVideoRequest $request, Entity $entity, EntityReviewVideo $video): RedirectResponse
    {
        $this->ensureBelongs($entity, $video);

        $changes = [];
        $title = $request->string('title')->trim()->value();

        if ($title !== '') {
            $changes['title'] = $title;
        }

        $newId = $request->verifiedVideoId();

        if ($video->source === EntityReviewVideo::SOURCE_MANUAL && $newId !== null && $newId !== $video->youtube_id) {
            if (EntityReviewVideo::query()->where('entity_id', $entity->id)->where('youtube_id', $newId)->exists()) {
                return redirect()->back()->withErrors(['url' => 'This video is already listed for the product.']);
            }

            $changes['youtube_id'] = $newId;

            if ($title === '') {
                $changes['title'] = (string) $request->verifiedTitle();
            }
        }

        $video->update($changes);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review video updated.']);

        return redirect()->back();
    }

    /**
     * A manual video is deleted. An automatic one is only hidden: if its row
     * were deleted, the daily search would add it back.
     */
    public function destroy(Entity $entity, EntityReviewVideo $video): RedirectResponse
    {
        $this->ensureBelongs($entity, $video);

        if ($video->source === EntityReviewVideo::SOURCE_MANUAL) {
            $video->delete();
            $message = 'Review video deleted.';
        } else {
            $video->update(['hidden_at' => now()]);
            $message = 'Automatic video hidden. It will not come back.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->back();
    }

    public function restore(Entity $entity, EntityReviewVideo $video): RedirectResponse
    {
        $this->ensureBelongs($entity, $video);

        $video->update(['hidden_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review video is visible again.']);

        return redirect()->back();
    }

    private function ensureBelongs(Entity $entity, EntityReviewVideo $video): void
    {
        abort_if($video->entity_id !== $entity->id, 404);
    }
}
