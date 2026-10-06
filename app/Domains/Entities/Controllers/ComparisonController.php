<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\EntityComparison;
use App\Domains\Search\Services\RobotsPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ComparisonController extends Controller
{
    public function __construct(protected EntityComparison $comparisons) {}

    /**
     * Side-by-side Sentimen Netijen for two entities (docs/31 Fase 3, ADR-012).
     */
    public function show(string $pair): Response|RedirectResponse
    {
        $slugs = $this->comparisons->parse($pair);
        abort_if($slugs === null, 404);

        $canonical = $this->comparisons->canonicalPair($slugs[0], $slugs[1]);
        if ($canonical !== $pair) {
            return redirect()->route('comparisons.show', ['pair' => $canonical], 301);
        }

        $entities = Entity::query()
            ->with('category')
            ->active()
            ->where('searchable', true)
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');
        abort_if($entities->count() !== 2, 404);

        $comparison = $this->comparisons->build($entities[$slugs[0]], $entities[$slugs[1]]);
        abort_if($comparison === null, 404);

        if (! $comparison['same_category'] || ! $this->comparisons->isCurated($canonical)) {
            RobotsPolicy::noindex();
        }

        return Inertia::render('Comparison/Show', [
            'pair' => $canonical,
            'indexable' => ! RobotsPolicy::isNoindex(),
            'comparison' => $comparison,
            'comparisonSeo' => $this->comparisons->seo($comparison, $canonical),
        ])->withViewData('robots', RobotsPolicy::get());
    }
}
