<?php

namespace App\Domains\Sponsorships\Controllers;

use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Domains\Sponsorships\Services\SponsorLeaderboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSponsorshipController extends Controller
{
    public function index(Request $request, SponsorLeaderboardService $leaderboardService): Response
    {
        $activePeriod = $leaderboardService->getActivePeriod();

        $periods = SponsorPeriod::query()->orderByDesc('starts_at')->get();
        $selectedPeriodId = $request->integer('period_id', $activePeriod->id);
        $selectedPeriod = SponsorPeriod::find($selectedPeriodId) ?? $activePeriod;

        $entries = SponsoredEntry::query()
            ->where('period_id', $selectedPeriod->id)
            ->with(['entity.category'])
            ->orderByDesc('settled_total_amount')
            ->paginate(20)
            ->withQueryString();

        $recentOrders = SponsorshipOrder::query()
            ->with(['user', 'sponsoredEntry.entity'])
            ->latest()
            ->limit(15)
            ->get();

        return Inertia::render('Admin/Sponsorship/Index', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'activePeriod' => $activePeriod,
            'entries' => $entries,
            'recentOrders' => $recentOrders,
        ]);
    }

    public function toggleEntryStatus(Request $request, SponsoredEntry $entry): RedirectResponse
    {
        $newStatus = match ($entry->status) {
            SponsoredEntryStatus::Active => SponsoredEntryStatus::Paused,
            SponsoredEntryStatus::Paused => SponsoredEntryStatus::Active,
            default => SponsoredEntryStatus::Active,
        };

        $entry->update(['status' => $newStatus]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Status entitas sponsor diperbarui menjadi {$newStatus->label()}."]);

        return back();
    }

    public function removeEntry(Request $request, SponsoredEntry $entry): RedirectResponse
    {
        $entry->update(['status' => SponsoredEntryStatus::Removed]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entitas sponsor telah dihapus dari papan.']);

        return back();
    }
}
