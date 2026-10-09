<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityCandidate;
use App\Domains\Entities\Requests\ApproveEntityCandidateRequest;
use App\Domains\Entities\Services\EntityCandidateApprover;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function approve(ApproveEntityCandidateRequest $request, EntityCandidate $entityCandidate, EntityCandidateApprover $approver): RedirectResponse
    {
        $approver->approve($entityCandidate, [
            'name' => $request->string('name')->value(),
            'entity_type' => $request->string('entity_type')->value(),
            'category_id' => $request->integer('category_id'),
            'parent_id' => $request->filled('parent_id') ? $request->integer('parent_id') : null,
            'aliases' => array_values(array_map('strval', (array) $request->validated('aliases', []))),
        ], $request->user()->id);

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
