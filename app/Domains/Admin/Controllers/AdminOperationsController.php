<?php

namespace App\Domains\Admin\Controllers;

use App\Domains\Ingestion\Jobs\DiscoverSourceDocumentsJob;
use App\Domains\Ingestion\Jobs\FetchSourceDocumentJob;
use App\Domains\Ingestion\Jobs\MatchEntitiesJob;
use App\Domains\Sources\Enums\DocumentState;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\CrawlState;
use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Models\UnmatchedMention;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOperationsController extends Controller
{
    /**
     * Display crawl states across sources.
     */
    public function crawlStates(Request $request): Response
    {
        $sourceId = $request->query('source_id');

        $query = CrawlState::query()
            ->with('source')
            ->latest('last_crawled_at');

        if ($sourceId) {
            $query->where('source_id', (int) $sourceId);
        }

        $states = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Operations/CrawlStates', [
            'states' => $states,
            'sources' => Source::query()->orderBy('name')->get(['id', 'name', 'key']),
            'filters' => [
                'source_id' => $sourceId ? (int) $sourceId : null,
            ],
        ]);
    }

    /**
     * Display ingestion failures with replay controls.
     */
    public function ingestionFailures(Request $request): Response
    {
        $sourceId = $request->query('source_id');
        $stage = $request->query('stage');
        $status = $request->query('status', 'unresolved');

        $query = IngestionFailure::query()
            ->with(['source', 'document', 'item'])
            ->latest('id');

        if ($sourceId) {
            $query->where('source_id', (int) $sourceId);
        }

        if ($stage) {
            $query->where('stage', $stage);
        }

        if ($status === 'unresolved') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        $failures = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Operations/IngestionFailures', [
            'failures' => $failures,
            'sources' => Source::query()->orderBy('name')->get(['id', 'name', 'key']),
            'stages' => ['discovery', 'fetch', 'extract', 'match', 'classify'],
            'filters' => [
                'source_id' => $sourceId ? (int) $sourceId : null,
                'stage' => $stage ?: null,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Display unmatched mentions for precision debugging.
     */
    public function unmatchedMentions(Request $request): Response
    {
        $sourceId = $request->query('source_id');
        $reason = $request->query('reason');

        $query = UnmatchedMention::query()
            ->with(['source', 'item'])
            ->latest('id');

        if ($sourceId) {
            $query->where('source_id', (int) $sourceId);
        }

        if ($reason) {
            $query->where('reason', $reason);
        }

        $mentions = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Operations/UnmatchedMentions', [
            'mentions' => $mentions,
            'sources' => Source::query()->orderBy('name')->get(['id', 'name', 'key']),
            'reasons' => ['entity_not_resolved', 'not_an_evaluation'],
            'filters' => [
                'source_id' => $sourceId ? (int) $sourceId : null,
                'reason' => $reason ?: null,
            ],
        ]);
    }

    /**
     * Replay a specific source item by resetting state and re-dispatching.
     */
    public function replayItem(SourceItem $sourceItem): RedirectResponse
    {
        $sourceItem->update([
            'processing_state' => ProcessingState::Pending,
        ]);

        IngestionFailure::query()
            ->where('source_item_id', $sourceItem->id)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => CarbonImmutable::now()]);

        MatchEntitiesJob::dispatch($sourceItem->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Item {$sourceItem->id} ({$sourceItem->external_id}) re-queued for processing."]);

        return back();
    }

    /**
     * Retry a specific recorded ingestion failure.
     */
    public function retryFailure(IngestionFailure $failure): RedirectResponse
    {
        if ($failure->source_item_id && ($item = SourceItem::find($failure->source_item_id))) {
            $item->update(['processing_state' => ProcessingState::Pending]);
            MatchEntitiesJob::dispatch($item->id);
        } elseif ($failure->source_document_id && ($doc = SourceDocument::find($failure->source_document_id))) {
            $doc->update(['state' => DocumentState::Discovered]);
            FetchSourceDocumentJob::dispatch($doc);
        } elseif ($failure->source) {
            DiscoverSourceDocumentsJob::dispatch($failure->source);
        }

        $failure->update([
            'resolved_at' => CarbonImmutable::now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Failure #{$failure->id} replayed and marked resolved."]);

        return back();
    }
}
