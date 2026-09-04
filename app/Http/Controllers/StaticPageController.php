<?php

namespace App\Http\Controllers;

use App\Domains\Sources\Models\Source;
use Inertia\Inertia;
use Inertia\Response;

class StaticPageController extends Controller
{
    /**
     * Display methodology page explaining scoring formulas and guardrails.
     */
    public function methodology(): Response
    {
        return Inertia::render('Pages/Methodology', [
            'scoring' => [
                'public_min_opinions' => (int) config('scoring.public_min_opinions', 30),
                'ranking_min_opinions' => (int) config('scoring.ranking_min_opinions', 100),
                'formula_version' => (string) config('scoring.formula_version', 'v1'),
            ],
        ]);
    }

    /**
     * Display data sources and crawler transparency page.
     */
    public function sources(): Response
    {
        $sources = Source::query()
            ->orderBy('priority')
            ->get()
            ->map(fn (Source $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'key' => $s->key,
                'source_type' => $s->source_type->value,
                'health_state' => $s->health_state->value,
                'is_operational' => $s->health_state->isOperational(),
                'enabled' => $s->enabled,
                'last_preflight_at' => $s->last_preflight_at?->diffForHumans(),
            ]);

        return Inertia::render('Pages/Sources', [
            'sources' => $sources,
        ]);
    }

    /**
     * Display about page.
     */
    public function about(): Response
    {
        return Inertia::render('Pages/About');
    }

    /**
     * Display terms of service page.
     */
    public function terms(): Response
    {
        return Inertia::render('Pages/Terms');
    }

    /**
     * Display privacy policy page.
     */
    public function privacy(): Response
    {
        return Inertia::render('Pages/Privacy');
    }
}
