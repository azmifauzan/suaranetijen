<?php

namespace App\Domains\Search\Controllers\Api;

use App\Domains\Search\Services\SearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        protected SearchService $searchService
    ) {}

    /**
     * Handle incoming search autocomplete / API requests.
     */
    public function index(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $category = $request->filled('category') ? (string) $request->query('category') : null;
        $limit = min(max((int) $request->query('limit', 10), 1), 50);

        $userId = $request->user()?->id;
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        $results = $this->searchService->search(
            query: $query,
            category: $category,
            limit: $limit,
            userId: $userId,
            sessionId: $sessionId,
            logQuery: true
        );

        return response()->json($results);
    }
}
