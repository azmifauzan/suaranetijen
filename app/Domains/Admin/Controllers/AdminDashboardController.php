<?php

namespace App\Domains\Admin\Controllers;

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
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
            ],
        ]);
    }
}
