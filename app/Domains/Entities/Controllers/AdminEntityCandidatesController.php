<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Models\EntityCandidate;
use App\Domains\Entities\Requests\ApproveEntityCandidateRequest;
use App\Domains\Entities\Services\TextNormalizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminEntityCandidatesController extends Controller
{
    /**
     * List pending entity candidates, ranked by combined signal strength.
     */
    public function index(): Response
    {
        $candidates = EntityCandidate::query()
            ->where('status', 'pending')
            ->with(['suggestedCategory:id,name', 'suggestedParentEntity:id,name'])
            ->orderByDesc('frequency_score')
            ->paginate(25);

        return Inertia::render('Admin/EntityCandidates/Index', [
            'candidates' => $candidates,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Entity::query()->where('type', 'brand')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Approve a candidate: create the Entity (+ primary alias + any
     * additional aliases) using the admin's (possibly edited) fields, and
     * link the candidate to it.
     */
    public function approve(ApproveEntityCandidateRequest $request, EntityCandidate $entityCandidate): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $entityCandidate, $request): void {
            $entity = Entity::create([
                'category_id' => $validated['category_id'],
                'parent_id' => $validated['parent_id'] ?? null,
                'type' => $validated['entity_type'],
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'status' => EntityStatus::Active,
            ]);

            $primaryNormalized = TextNormalizer::normalize($entity->name);

            EntityAlias::create([
                'entity_id' => $entity->id,
                'alias' => $entity->name,
                'normalized_alias' => $primaryNormalized,
                'alias_type' => AliasType::Primary,
            ]);

            $seenNormalized = [$primaryNormalized => true];

            foreach (($validated['aliases'] ?? []) as $alias) {
                $alias = trim((string) $alias);
                if ($alias === '') {
                    continue;
                }

                $normalized = TextNormalizer::normalize($alias);
                if (isset($seenNormalized[$normalized])) {
                    continue;
                }
                $seenNormalized[$normalized] = true;

                EntityAlias::create([
                    'entity_id' => $entity->id,
                    'alias' => $alias,
                    'normalized_alias' => $normalized,
                    'alias_type' => AliasType::CommonVariant,
                ]);
            }

            $entityCandidate->update([
                'status' => 'approved',
                'entity_id' => $entity->id,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entity created from candidate.']);

        return redirect()->back();
    }

    /**
     * Dismiss a candidate permanently — it never resurfaces on a later scan.
     */
    public function reject(Request $request, EntityCandidate $entityCandidate): RedirectResponse
    {
        $entityCandidate->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Candidate dismissed.']);

        return redirect()->back();
    }
}
