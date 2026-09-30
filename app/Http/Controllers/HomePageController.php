<?php

namespace App\Http\Controllers;

use App\Domains\Entities\Services\HomepageCategoryBlockService;
use App\Domains\Search\Services\SearchSuggestionService;
use App\Domains\Sponsorships\Services\SponsorLeaderboardService;
use Inertia\Inertia;
use Inertia\Response;

class HomePageController extends Controller
{
    /**
     * Handle the incoming request for the homepage.
     */
    public function __invoke(
        SearchSuggestionService $searchSuggestionService,
        SponsorLeaderboardService $sponsorLeaderboardService,
        HomepageCategoryBlockService $categoryBlockService
    ): Response {
        return Inertia::render('Welcome', [
            'categoryBlocks' => $categoryBlockService->getCategoryBlocks(),
            'popularTopics' => $categoryBlockService->getPopularTopics(),
            'searchSuggestions' => $searchSuggestionService->getSuggestions(),
            'topLeaderboard' => $sponsorLeaderboardService->getHomepageTeaser(10),
            'sponsorTeaser' => $sponsorLeaderboardService->getHomepageTeaser(10),
        ]);
    }
}
