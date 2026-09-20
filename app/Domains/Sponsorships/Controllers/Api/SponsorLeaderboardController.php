<?php

namespace App\Domains\Sponsorships\Controllers\Api;

use App\Domains\Sponsorships\Services\SponsorLeaderboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorLeaderboardController extends Controller
{
    public function index(Request $request, SponsorLeaderboardService $leaderboardService): JsonResponse
    {
        $board = $leaderboardService->resolveBoard($request->query('period'));

        return response()->json([
            'period' => $board['period'],
            'data' => $board['leaderboard'],
        ]);
    }
}
