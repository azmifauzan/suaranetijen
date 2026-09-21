<?php

namespace App\Domains\Sponsorships\Services;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Services\ScoreCalculator;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Enums\SponsorPeriodStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SponsorLeaderboardService
{
    /**
     * Pseudo period key for the cross-period, never-resetting archive board (docs/26).
     * Not a row in sponsor_periods — periods stay weekly rows only.
     */
    public const ALL_TIME_KEY = 'all';

    /**
     * Ensure the active weekly period exists, closing expired periods if necessary.
     */
    public function ensureCurrentWeeklyPeriod(): SponsorPeriod
    {
        $now = CarbonImmutable::now();
        $startOfWeek = $now->startOfWeek();
        $endOfWeek = $now->endOfWeek();
        $key = $now->format('o-\wW');
        $name = 'Minggu '.$now->isoWeek().' ('.$now->translatedFormat('M Y').')';

        // Close any past active weekly periods that ended before this week started
        SponsorPeriod::query()
            ->where('status', SponsorPeriodStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $startOfWeek)
            ->update(['status' => SponsorPeriodStatus::Closed]);

        // createOrFirst, not firstOrCreate: two concurrent requests crossing a week boundary
        // must not race two inserts past the unique `key` constraint.
        /** @var SponsorPeriod $period */
        $period = SponsorPeriod::query()->createOrFirst(
            ['key' => $key],
            [
                'name' => $name,
                'starts_at' => $startOfWeek,
                'ends_at' => $endOfWeek,
                'status' => SponsorPeriodStatus::Active,
            ]
        );

        if ($period->status !== SponsorPeriodStatus::Active) {
            $period->update(['status' => SponsorPeriodStatus::Active]);
        }

        return $period;
    }

    /**
     * Get the active sponsor period.
     */
    public function getActivePeriod(): SponsorPeriod
    {
        $period = SponsorPeriod::query()
            ->where('status', SponsorPeriodStatus::Active)
            ->orderByDesc('starts_at')
            ->first();

        return $period ?? $this->ensureCurrentWeeklyPeriod();
    }

    /**
     * Get list of all periods for public board selector.
     *
     * @return Collection<int, array{id: int, key: string, name: string, status: string, is_active: bool}>
     */
    public function getPeriods(): Collection
    {
        $this->ensureCurrentWeeklyPeriod();

        $weeklyPeriods = SponsorPeriod::query()
            ->where(function ($q) {
                $q->where('status', SponsorPeriodStatus::Active)
                    ->orWhereHas('entries', function ($eq) {
                        $eq->where('settled_total_amount', '>', 0);
                    });
            })
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (SponsorPeriod $period) => [
                'id' => $period->id,
                'key' => $period->key,
                'name' => $period->name,
                'status' => $period->status->value,
                'is_active' => $period->status === SponsorPeriodStatus::Active,
            ]);

        $allTimeEntry = collect([[
            'id' => 0,
            'key' => self::ALL_TIME_KEY,
            'name' => 'Semua Waktu',
            'status' => SponsorPeriodStatus::Closed->value,
            'is_active' => false,
        ]]);

        return $allTimeEntry->concat($weeklyPeriods);
    }

    /**
     * Resolve a period selector value ('all', a period key, or none) into a period descriptor
     * plus its ranked leaderboard, in one call for the public board/API to share.
     *
     * @return array{period: array<string, mixed>, leaderboard: Collection<int, array<string, mixed>>}
     */
    public function resolveBoard(?string $periodKey, int $limit = 50): array
    {
        if ($periodKey === self::ALL_TIME_KEY) {
            return [
                'period' => [
                    'id' => 0,
                    'key' => self::ALL_TIME_KEY,
                    'name' => 'Semua Waktu',
                    'starts_at' => null,
                    'ends_at' => null,
                    'is_active' => false,
                ],
                'leaderboard' => $this->getAllTimeLeaderboard($limit),
            ];
        }

        $activePeriod = $this->getActivePeriod();
        $period = $periodKey
            ? SponsorPeriod::query()->where('key', $periodKey)->first() ?? $activePeriod
            : $activePeriod;

        return [
            'period' => [
                'id' => $period->id,
                'key' => $period->key,
                'name' => $period->name,
                'starts_at' => $period->starts_at?->toIso8601String(),
                'ends_at' => $period->ends_at?->toIso8601String(),
                'is_active' => $period->id === $activePeriod->id,
            ],
            'leaderboard' => $this->getLeaderboard($period, $limit),
        ];
    }

    /**
     * Get ranked leaderboard entries for a given period.
     * Ranking rules (docs/26):
     * 1. settled_total_amount descending
     * 2. first_settled_at ascending (earlier date wins equal totals)
     * 3. id ascending (stable deterministic tie-breaker)
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getLeaderboard(?SponsorPeriod $period = null, int $limit = 50): Collection
    {
        $period ??= $this->getActivePeriod();

        $entries = SponsoredEntry::query()
            ->where('period_id', $period->id)
            ->where('status', SponsoredEntryStatus::Active)
            ->where('settled_total_amount', '>', 0)
            ->whereHas('entity', fn ($q) => $q->where('status', EntityStatus::Active)->where('searchable', true))
            ->with([
                'entity' => function ($query): void {
                    $query->with([
                        'category',
                        'ratingSnapshot',
                        'sentimentSnapshots' => fn ($q) => $q->where('period', Period::OneYear->value),
                    ]);
                },
            ])
            ->orderByDesc('settled_total_amount')
            ->orderBy('first_settled_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $entries->values()->map(fn (SponsoredEntry $entry, int $index) => $this->presentRow(
            $entry->id,
            $entry->entity,
            (int) $entry->settled_total_amount,
            $entry->first_settled_at,
            (int) $entry->clicks_count,
            (int) $entry->views_count,
            $index + 1,
        ));
    }

    /**
     * All-time archive board: sponsor totals summed across every weekly period, never reset.
     * Same ranking rules as a single period (docs/26): total desc, earliest contribution asc,
     * entity ID asc as the final tie-breaker. Only entries currently `Active` count, same
     * moderation semantics as the weekly board — a paused/removed entry drops out of both.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getAllTimeLeaderboard(int $limit = 50): Collection
    {
        $rows = DB::table('sponsored_entries')
            ->join('entities', 'entities.id', '=', 'sponsored_entries.entity_id')
            ->where('sponsored_entries.status', SponsoredEntryStatus::Active->value)
            ->where('entities.status', EntityStatus::Active->value)
            ->where('entities.searchable', true)
            ->groupBy('sponsored_entries.entity_id')
            ->havingRaw('SUM(sponsored_entries.settled_total_amount) > 0')
            ->selectRaw('sponsored_entries.entity_id as entity_id')
            ->selectRaw('SUM(sponsored_entries.settled_total_amount) as total_amount')
            ->selectRaw('MIN(sponsored_entries.first_settled_at) as first_settled_at')
            ->selectRaw('SUM(sponsored_entries.clicks_count) as clicks_count')
            ->selectRaw('SUM(sponsored_entries.views_count) as views_count')
            ->orderByDesc('total_amount')
            ->orderBy('first_settled_at')
            ->orderBy('sponsored_entries.entity_id')
            ->limit($limit)
            ->get();

        $entities = Entity::query()
            ->whereIn('id', $rows->pluck('entity_id'))
            ->with(['category', 'ratingSnapshot', 'sentimentSnapshots' => fn ($q) => $q->where('period', Period::OneYear->value)])
            ->get()
            ->keyBy('id');

        return $rows->values()
            ->filter(fn ($row) => $entities->has((int) $row->entity_id))
            ->map(fn ($row, int $index) => $this->presentRow(
                (int) $row->entity_id,
                $entities->get((int) $row->entity_id),
                (int) $row->total_amount,
                $row->first_settled_at ? CarbonImmutable::parse($row->first_settled_at) : null,
                (int) $row->clicks_count,
                (int) $row->views_count,
                $index + 1,
            ))
            ->values();
    }

    /**
     * Get aggregate statistics for the leaderboard (total listings, total amount, clicks, views).
     *
     * @return array{total_listings: int, total_amount: int, total_clicks: int, total_views: int, highest_bid: int}
     */
    public function getBoardStats(?SponsorPeriod $period = null): array
    {
        $query = SponsoredEntry::query()
            ->where('status', SponsoredEntryStatus::Active)
            ->where('settled_total_amount', '>', 0);

        if ($period !== null) {
            $query->where('period_id', $period->id);
        }

        $totalListings = (int) (clone $query)->count();
        $totalAmount = (int) (clone $query)->sum('settled_total_amount');
        $totalClicks = (int) (clone $query)->sum('clicks_count');
        $totalViews = (int) (clone $query)->sum('views_count');
        $highestBid = (int) ((clone $query)->max('settled_total_amount') ?? 0);

        return [
            'total_listings' => $totalListings,
            'total_amount' => $totalAmount,
            'total_clicks' => $totalClicks,
            'total_views' => $totalViews,
            'highest_bid' => $highestBid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(
        int $id,
        Entity $entity,
        int $settledTotalAmount,
        ?CarbonImmutable $firstSettledAt,
        int $clicksCount,
        int $viewsCount,
        int $rank,
    ): array {
        $snapshot = $entity->sentimentSnapshots->first();
        $ratingSnap = $entity->ratingSnapshot;
        $isPublicScore = $snapshot && ScoreCalculator::isPublicScoreEligible((int) $snapshot->opinion_count);

        return [
            'id' => $id,
            'rank' => $rank,
            'entity_id' => $entity->id,
            'name' => $entity->name,
            'slug' => $entity->slug,
            'type_label' => $entity->type->label(),
            'category_name' => $entity->category->name,
            'website_url' => $entity->website_url,
            'description' => $entity->description,
            'settled_total_amount' => $settledTotalAmount,
            'first_settled_at' => $firstSettledAt?->toIso8601String(),
            'clicks_count' => $clicksCount,
            'views_count' => $viewsCount,
            'sentiment_score' => $isPublicScore ? (float) $snapshot->score : null,
            'opinion_count' => $snapshot ? (int) $snapshot->opinion_count : 0,
            'rating_average' => $ratingSnap?->rating_average ? (float) $ratingSnap->rating_average : null,
            'rating_count' => $ratingSnap ? (int) $ratingSnap->rating_count : 0,
        ];
    }

    /**
     * Always returns a payload, even with an empty board — an empty board still needs to
     * invite the *first* sponsor (docs/26), not disappear until one already exists.
     *
     * @return array<string, mixed>
     */
    public function getHomepageTeaser(int $limit = 13): array
    {
        $period = $this->getActivePeriod();
        $leaderboard = $this->getLeaderboard($period, $limit);

        $totalAmount = (int) SponsoredEntry::query()
            ->where('period_id', $period->id)
            ->where('status', SponsoredEntryStatus::Active)
            ->sum('settled_total_amount');

        return [
            'period_key' => $period->key,
            'period_name' => $period->name,
            'total_settled_amount' => $totalAmount,
            'is_empty' => $leaderboard->isEmpty(),
            'top_entry' => $leaderboard->first(),
            'top_entries' => $leaderboard->all(),
        ];
    }

    /**
     * Calculate the minimum contribution amount needed to overtake a target position or entry.
     */
    public function calculateAmountToOvertake(SponsorPeriod $period, int $targetRank, ?int $currentEntityId = null): int
    {
        $entries = $this->getLeaderboard($period, $targetRank);
        $targetEntry = $entries->firstWhere('rank', $targetRank);

        $increment = (int) config('sponsorship.increment_amount', 1);
        $minAmount = (int) config('sponsorship.min_amount', 1000);

        if (! $targetEntry) {
            return $minAmount;
        }

        $targetTotal = (int) $targetEntry['settled_total_amount'];
        $currentTotal = 0;

        if ($currentEntityId) {
            $existing = SponsoredEntry::query()
                ->where('period_id', $period->id)
                ->where('entity_id', $currentEntityId)
                ->first();
            if ($existing) {
                $currentTotal = (int) $existing->settled_total_amount;
            }
        }

        $needed = ($targetTotal + $increment) - $currentTotal;

        return max($minAmount, $needed);
    }

    /**
     * Predict what rank a target entity would reach with a given additional amount.
     */
    public function predictPosition(SponsorPeriod $period, int $entityId, int $additionalAmount): int
    {
        $currentEntry = SponsoredEntry::query()
            ->where('period_id', $period->id)
            ->where('entity_id', $entityId)
            ->first();

        $newTotal = ($currentEntry ? $currentEntry->settled_total_amount : 0) + $additionalAmount;
        $effectiveFirstSettledAt = $currentEntry !== null && $currentEntry->first_settled_at !== null
            ? $currentEntry->first_settled_at
            : CarbonImmutable::now();

        // Count how many other entries would rank higher than this new total:
        // Rank higher if:
        // 1. settled_total_amount > newTotal
        // 2. settled_total_amount == newTotal AND first_settled_at < effectiveFirstSettledAt
        $higherCount = SponsoredEntry::query()
            ->where('period_id', $period->id)
            ->where('status', SponsoredEntryStatus::Active)
            ->where('entity_id', '!=', $entityId)
            ->where(function ($query) use ($newTotal, $effectiveFirstSettledAt): void {
                $query->where('settled_total_amount', '>', $newTotal)
                    ->orWhere(function ($q) use ($newTotal, $effectiveFirstSettledAt): void {
                        $q->where('settled_total_amount', '=', $newTotal)
                            ->whereNotNull('first_settled_at')
                            ->where('first_settled_at', '<', $effectiveFirstSettledAt);
                    });
            })
            ->count();

        return $higherCount + 1;
    }
}
