<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Models\EntityCandidate;
use App\Domains\Entities\Models\EntitySubmission;
use App\Domains\Entities\Requests\StoreEntitySubmissionRequest;
use App\Domains\Entities\Services\TextNormalizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EntitySubmissionController extends Controller
{
    /**
     * The user's own submissions plus the form's option lists.
     */
    public function index(Request $request): Response
    {
        $submissions = EntitySubmission::query()
            ->where('user_id', $request->user()->id)
            ->with(['candidate:id,status,entity_id,review_note', 'candidate.entity:id,slug'])
            ->latest()
            ->get()
            ->map(fn (EntitySubmission $submission): array => [
                'id' => $submission->id,
                'name' => $submission->name,
                'entity_type' => $submission->entity_type,
                'status' => $submission->candidate === null ? 'pending' : $submission->candidate->status,
                'review_note' => $submission->candidate?->review_note,
                'entity_slug' => $submission->candidate?->entity?->slug,
                'created_at' => $submission->created_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/EntitySubmissions', [
            'submissions' => $submissions,
            'categories' => Category::query()->active()->excludePublicFigure()->whereDoesntHave('children')->orderBy('name')->get(['id', 'name']),
            'brands' => Entity::query()->active()->where('type', EntityType::Brand->value)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Join the entity candidate review queue; an admin still decides.
     */
    public function store(StoreEntitySubmissionRequest $request): RedirectResponse
    {
        $name = trim($request->string('name')->value());
        $normalized = TextNormalizer::normalize($name);

        $existing = EntityAlias::query()->where('normalized_alias', $normalized)->first();

        if ($existing !== null) {
            $publicEntity = Entity::query()->active()->find($existing->entity_id, ['id', 'slug', 'name']);

            throw ValidationException::withMessages([
                'name' => $publicEntity !== null
                    ? '"'.$publicEntity->name.'" sudah ada di SuaraNetijen: '.route('entities.show', $publicEntity->slug)
                    : 'Nama ini sudah terdaftar atau sedang ditinjau.',
            ]);
        }

        $isParentAllowed = $request->input('entity_type') !== EntityType::Brand->value;
        $parentId = $isParentAllowed && $request->filled('parent_entity_id') ? $request->integer('parent_entity_id') : null;

        $candidate = EntityCandidate::query()->createOrFirst(
            ['normalized_term' => $normalized],
            [
                'raw_terms' => [$name],
                'source_types' => ['user_submission'],
                'frequency_score' => 0,
                'suggested_name' => $name,
                'suggested_entity_type' => $request->string('entity_type')->value(),
                'suggested_category_id' => $request->integer('category_id'),
                'suggested_parent_entity_id' => $parentId,
            ],
        );

        $submission = EntitySubmission::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'entity_candidate_id' => $candidate->id],
            [
                'name' => $name,
                'entity_type' => $request->string('entity_type')->value(),
                'category_id' => $request->integer('category_id'),
                'parent_entity_id' => $parentId,
                'website_url' => $request->input('website_url'),
                'description' => $request->input('description'),
                'is_owner' => $request->boolean('is_owner'),
            ],
        );

        if ($submission->wasRecentlyCreated) {
            $candidate->increment('frequency_score');

            if (! in_array('user_submission', $candidate->source_types, true)) {
                $candidate->update(['source_types' => [...$candidate->source_types, 'user_submission']]);
            }

            Log::info('entity_submission.created', ['user_id' => $request->user()->id, 'entity_candidate_id' => $candidate->id]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usulan terkirim. Admin akan meninjaunya.']);

        return redirect()->route('entity-submissions.index');
    }
}
