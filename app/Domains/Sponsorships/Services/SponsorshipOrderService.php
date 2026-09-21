<?php

namespace App\Domains\Sponsorships\Services;

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Enums\SponsorshipOrderStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SponsorshipOrderService
{
    public function __construct(
        private SponsorLeaderboardService $leaderboardService,
        private SumopodService $sumopodService
    ) {}

    /**
     * Create a sponsorship order for an existing entity and initiate Sumopod QRIS payment.
     *
     * @throws ValidationException|Throwable
     */
    public function createOrder(
        User $user,
        Entity $entity,
        int $amount,
        ?string $redirectUrl = null,
        ?string $websiteUrl = null
    ): SponsorshipOrder {
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

        if ($websiteUrl) {
            $normalizedWebsiteUrl = ! str_starts_with($websiteUrl, 'http://') && ! str_starts_with($websiteUrl, 'https://')
                ? 'https://'.$websiteUrl
                : $websiteUrl;

            if (filter_var($normalizedWebsiteUrl, FILTER_VALIDATE_URL) && (! $entity->website_url || ! filter_var($entity->website_url, FILTER_VALIDATE_URL))) {
                $entity->update(['website_url' => $normalizedWebsiteUrl]);
            }
        }

        $this->assertValidAmount($amount);

        $period = $this->leaderboardService->ensureCurrentWeeklyPeriod();

        $order = DB::transaction(function () use ($user, $entity, $period, $amount): SponsorshipOrder {
            $entry = $this->findOrCreateEntry($period->id, $entity->id);

            return $this->createPendingOrder($user, $entry, $amount);
        });

        return $this->initiatePayment($order, $amount, $redirectUrl);
    }

    /**
     * Create a sponsorship order for a URL that doesn't match any existing entity (docs/26): the
     * entity is created now, but `Disabled`/non-searchable/non-rankable, so it's invisible to
     * search, the crawler matcher, and sentiment ranking (all three already scope on
     * `active()`/`searchable()`) until the payment actually confirms — `ProcessSponsorshipRelayWebhook`
     * flips it to `Active`/searchable/rankable on `payment.completed`, never before. An abandoned
     * or failed order simply leaves an inert, invisible row rather than a real listing.
     *
     * @throws ValidationException|Throwable
     */
    public function createOrderForNewEntity(
        User $user,
        string $name,
        int $categoryId,
        string $sourceUrl,
        int $amount,
        ?string $redirectUrl = null,
        ?string $description = null
    ): SponsorshipOrder {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'new_entity_name' => 'Nama entitas wajib diisi.',
            ]);
        }

        /** @var Category|null $category */
        $category = Category::query()->find($categoryId);
        if (! $category) {
            throw ValidationException::withMessages([
                'new_entity_category_id' => 'Kategori tidak ditemukan.',
            ]);
        }

        $this->assertValidAmount($amount);

        $period = $this->leaderboardService->ensureCurrentWeeklyPeriod();

        $normalizedUrl = ! str_starts_with($sourceUrl, 'http://') && ! str_starts_with($sourceUrl, 'https://')
            ? 'https://'.$sourceUrl
            : $sourceUrl;

        $order = DB::transaction(function () use ($user, $name, $category, $normalizedUrl, $amount, $period, $description): SponsorshipOrder {
            $entity = Entity::create([
                'category_id' => $category->id,
                'type' => EntityType::Brand,
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                // The sponsor's own description when they supplied one (pre-filled from the site's
                // meta description, then edited), otherwise the submitted URL as before.
                'description' => $description ?: $normalizedUrl,
                'website_url' => $normalizedUrl,
                'status' => EntityStatus::Disabled,
                'searchable' => false,
                'rankable' => false,
            ]);

            EntityAlias::create([
                'entity_id' => $entity->id,
                'alias' => $entity->name,
                'normalized_alias' => TextNormalizer::normalize($entity->name),
                'alias_type' => AliasType::Primary,
            ]);

            $entry = $this->findOrCreateEntry($period->id, $entity->id);

            return $this->createPendingOrder($user, $entry, $amount);
        });

        return $this->initiatePayment($order, $amount, $redirectUrl);
    }

    private function assertValidAmount(int $amount): void
    {
        $minAmount = (int) config('sponsorship.min_amount', 1000);

        if ($amount < $minAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal sponsor minimal adalah Rp'.number_format($minAmount, 0, ',', '.').'.',
            ]);
        }
    }

    private function findOrCreateEntry(int $periodId, int $entityId): SponsoredEntry
    {
        // createOrFirst, not firstOrCreate: two users sponsoring the same brand-new entity
        // at once must not race two inserts past the unique (period_id, entity_id) constraint.
        return SponsoredEntry::query()->createOrFirst(
            [
                'period_id' => $periodId,
                'entity_id' => $entityId,
            ],
            [
                'settled_total_amount' => 0,
                'status' => SponsoredEntryStatus::Pending,
                'clicks_count' => 0,
            ]
        );
    }

    private function createPendingOrder(User $user, SponsoredEntry $entry, int $amount): SponsorshipOrder
    {
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
        $order->update(['provider_order_id' => $prefix.$order->id]);

        return $order;
    }

    private function initiatePayment(SponsorshipOrder $order, int $amount, ?string $redirectUrl): SponsorshipOrder
    {
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

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Entity::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
