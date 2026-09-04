<?php

namespace App\Domains\Admin\Controllers;

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Sources\Models\CrawlState;
use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\UnmatchedMention;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin overview dashboard.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_entities' => Entity::count(),
                'total_categories' => Category::count(),
                'total_aliases' => EntityAlias::count(),
                'total_sources' => Source::count(),
                'enabled_sources' => Source::query()->enabled()->count(),
                'total_crawl_states' => CrawlState::count(),
                'unresolved_failures' => IngestionFailure::query()->whereNull('resolved_at')->count(),
                'total_unmatched' => UnmatchedMention::count(),
            ],
        ]);
    }
}
