<?php

namespace App\Domains\Search\Controllers;

use App\Domains\Entities\Models\Category;
use App\Domains\Search\Services\SearchService;
use App\Domains\Sponsorships\Services\SponsorLeaderboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchPageController extends Controller
{
    public function __construct(
        protected SearchService $searchService,
        protected SponsorLeaderboardService $sponsorLeaderboardService
    ) {}

    /**
     * Render the public search results page.
     */
    public function index(Request $request): Response
    {
        $query = (string) $request->query('q', '');
        $categorySlug = $request->filled('category') ? (string) $request->query('category') : null;

        $userId = $request->user()?->id;
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        $searchResults = $this->searchService->search(
            query: $query,
            category: $categorySlug,
            limit: 30,
            userId: $userId,
            sessionId: $sessionId,
            logQuery: true
        );

        $categories = Category::active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Search/Index', [
            'query' => $query,
            'results' => $searchResults['data'],
            'meta' => $searchResults['meta'],
            'categories' => $categories,
            'selectedCategory' => $categorySlug,
            // Sponsor visibility (docs/26): same teaser as the homepage, never affecting
            // result ordering or relevance — a separate, clearly labelled band only.
            'sponsorTeaser' => $this->sponsorLeaderboardService->getHomepageTeaser(3),
        ]);
    }
}
