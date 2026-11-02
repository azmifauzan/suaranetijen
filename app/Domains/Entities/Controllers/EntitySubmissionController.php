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
use App\Domains\Sponsorships\Enums\SponsorshipOrderStatus;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EntitySubmissionController extends Controller
{
    /**
     * The user's own submissions and sponsor orders in one list, plus the form's option lists.
     * A sponsor order skips admin review: once paid it is live, so it reads "Disetujui".
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $submissions = EntitySubmission::query()
            ->where('user_id', $userId)
            ->with(['candidate:id,status,entity_id,review_note', 'candidate.entity:id,slug'])
            ->get()
            ->map(function (EntitySubmission $submission): array {
                $status = $submission->candidate === null ? 'pending' : $submission->candidate->status;

                return [
                    'key' => 'submission-'.$submission->id,
                    'kind' => 'submission',
                    'name' => $submission->name,
                    'entity_type' => $submission->entity_type,
                    'status' => $status,
                    'status_label' => match ($status) {
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => 'Menunggu review',
                    },
                    'review_note' => $submission->candidate?->review_note,
                    'entity_slug' => $status === 'approved' ? $submission->candidate?->entity?->slug : null,
                    'created_at' => $submission->created_at,
                ];
            });

        $sponsorOrders = SponsorshipOrder::query()
            ->where('user_id', $userId)
            ->with(['sponsoredEntry.entity:id,name,slug,type', 'sponsoredEntry.period:id,name'])
            ->get()
            ->map(function (SponsorshipOrder $order): array {
                $entity = $order->sponsoredEntry->entity;
                $isPaid = $order->status === SponsorshipOrderStatus::Paid;
                $canPay = $order->status === SponsorshipOrderStatus::Pending
                    && ($order->expires_at === null || $order->expires_at->isFuture());

                return [
                    'key' => 'sponsor-'.$order->id,
                    'kind' => 'sponsor',
                    'name' => $entity->name,
                    'entity_type' => $entity->type->value,
                    'status' => match ($order->status) {
                        SponsorshipOrderStatus::Paid => 'approved',
                        SponsorshipOrderStatus::Pending => 'pending',
                        default => 'rejected',
                    },
                    'status_label' => $isPaid ? 'Disetujui' : $order->status->label(),
                    'review_note' => null,
                    // An unpaid order's new entity is still disabled and 404s publicly.
                    'entity_slug' => $isPaid ? $entity->slug : null,
                    'amount' => $order->amount,
                    'period_name' => $order->sponsoredEntry->period->name,
                    'payment_link_url' => $canPay ? $order->payment_link_url : null,
                    'created_at' => $order->created_at,
                ];
            });

        $items = $submissions->concat($sponsorOrders)
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (array $item): array => [...$item, 'created_at' => $item['created_at']?->toIso8601String()]);

        return Inertia::render('EntitySubmissions/Index', [
            'submissions' => $items,
            'categories' => Category::query()->active()->excludePublicFigure()->whereDoesntHave('children')->orderBy('name')->get(['id', 'name']),
            'brands' => Entity::query()->active()->where('type', EntityType::Brand->value)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Join the entity candidate review queue; an admin still decides.
     */
    public function store(StoreEntitySubmissionRequest $request): SymfonyResponse
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

        if ($request->boolean('sponsor')) {
            // No review-queue candidate: the sponsor checkout creates the entity itself and
            // publishes it once payment confirms (docs/26).
            return Inertia::location(route('leaderboard.index', array_filter([
                'url' => $request->input('website_url'),
                'name' => $name,
                'category_id' => $request->integer('category_id'),
                'type' => $request->string('entity_type')->value(),
                'description' => $request->input('description'),
            ])));
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
