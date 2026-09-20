<?php

namespace App\Domains\Sponsorships\Services;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Enums\SponsorshipOrderStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SponsorshipOrderService
{
    public function __construct(
        private SponsorLeaderboardService $leaderboardService,
        private SumopodService $sumopodService
    ) {}

    /**
     * Create a new sponsorship order and initiate Sumopod QRIS payment.
     *
     * @throws ValidationException|Throwable
     */
    public function createOrder(User $user, Entity $entity, int $amount, ?string $redirectUrl = null): SponsorshipOrder
    {
        if ($entity->status !== EntityStatus::Active || ! $entity->searchable) {
            throw ValidationException::withMessages([
                'entity_id' => 'Entitas yang dipilih tidak aktif atau tidak dapat disponsori.',
            ]);
        }

        // Public-figure/political sponsorship is excluded from MVP pending a separate policy
        // review (docs/26).
        if ($entity->type === EntityType::Person) {
            throw ValidationException::withMessages([
                'entity_id' => 'Entitas tokoh publik belum dapat disponsori.',
            ]);
        }

        $minAmount = (int) config('sponsorship.min_amount', 1000);
        $increment = (int) config('sponsorship.increment_amount', 1000);

        if ($amount < $minAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal sponsor minimal adalah Rp'.number_format($minAmount, 0, ',', '.').'.',
            ]);
        }

        if ($increment > 0 && ($amount % $increment) !== 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal sponsor harus kelipatan Rp'.number_format($increment, 0, ',', '.').'.',
            ]);
        }

        $period = $this->leaderboardService->ensureCurrentWeeklyPeriod();

        /** @var SponsorshipOrder $order */
        $order = DB::transaction(function () use ($user, $entity, $period, $amount): SponsorshipOrder {
            // createOrFirst, not firstOrCreate: two users sponsoring the same brand-new entity
            // at once must not race two inserts past the unique (period_id, entity_id) constraint.
            $entry = SponsoredEntry::query()->createOrFirst(
                [
                    'period_id' => $period->id,
                    'entity_id' => $entity->id,
                ],
                [
                    'settled_total_amount' => 0,
                    'status' => SponsoredEntryStatus::Pending,
                    'clicks_count' => 0,
                ]
            );

            $order = SponsorshipOrder::query()->create([
                'sponsored_entry_id' => $entry->id,
                'user_id' => $user->id,
                'provider' => 'sumopod',
                'provider_order_id' => 'temp-'.uniqid('', true),
                'amount' => $amount,
                'status' => SponsorshipOrderStatus::Pending,
                'expires_at' => CarbonImmutable::now()->addHours(24),
            ]);

            $prefix = (string) config('sponsorship.sumopod.order_prefix', 'SNT-SPN-');
            $providerOrderId = $prefix.$order->id;

            $order->update(['provider_order_id' => $providerOrderId]);

            return $order;
        });

        $returnUrl = $redirectUrl ?: url("/sponsor?order_id={$order->id}");

        try {
            $payment = $this->sumopodService->createPayment([
                'order_id' => $order->provider_order_id,
                'amount' => $amount,
                'redirect_url' => $returnUrl,
            ]);

            $order->update([
                'provider_payment_id' => $payment['payment_id'],
                'payment_link_url' => $payment['payment_link_url'],
            ]);

            return $order;
        } catch (Throwable $e) {
            $order->update([
                'status' => SponsorshipOrderStatus::Failed,
                'audit_payload' => ['creation_error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }
}
