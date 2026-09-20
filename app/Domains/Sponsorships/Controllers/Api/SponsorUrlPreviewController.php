<?php

namespace App\Domains\Sponsorships\Controllers\Api;

use App\Domains\Search\Services\SearchService;
use App\Domains\Sponsorships\Services\FetchUrlPreview;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Step 1 of the sponsor checkout flow (docs/26): the user pastes a URL, this fetches its title,
 * then searches existing entities by that title so the user picks a match — never creates a
 * new entity. Mirrors Pamerin's URL-first progressive disclosure.
 */
class SponsorUrlPreviewController extends Controller
{
    public function __invoke(Request $request, FetchUrlPreview $fetcher, SearchService $searchService): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        try {
            $preview = $fetcher->handle((string) $request->input('url'));
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $matches = $searchService->search(
            query: $preview['title'],
            category: null,
            limit: 5,
            userId: $request->user()?->id,
            sessionId: null,
            logQuery: false
        );

        return response()->json([
            'preview' => $preview,
            'candidates' => $matches['data'],
        ]);
    }
}
