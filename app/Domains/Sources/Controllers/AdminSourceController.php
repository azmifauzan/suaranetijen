<?php

namespace App\Domains\Sources\Controllers;

use App\Domains\Sources\Models\Source;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminSourceController extends Controller
{
    /**
     * Display the sources registry list with kill-switch status.
     */
    public function index(): Response
    {
        $sources = Source::query()
            ->withCount(['documents', 'items'])
            ->orderBy('priority')
            ->get()
            ->map(fn (Source $source) => [
                'id' => $source->id,
                'key' => $source->key,
                'name' => $source->name,
                'adapter' => class_basename($source->adapter),
                'source_type' => $source->source_type->value,
                'enabled' => $source->enabled,
                'priority' => $source->priority,
                'health_state' => $source->health_state->value,
                'is_operational' => $source->health_state->isOperational(),
                'last_preflight_at' => $source->last_preflight_at?->toIso8601String(),
                'documents_count' => $source->documents_count,
                'items_count' => $source->items_count,
            ]);

        return Inertia::render('Admin/Sources/Index', [
            'sources' => $sources,
        ]);
    }

    /**
     * Toggle the enabled kill switch for the given source.
     */
    public function toggleStatus(Source $source): RedirectResponse
    {
        $source->update([
            'enabled' => ! $source->enabled,
        ]);

        $status = $source->enabled ? 'enabled' : 'disabled';

        return back()->with('success', "Source '{$source->name}' has been {$status}.");
    }
}
