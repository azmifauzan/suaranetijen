<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Ratings\Models\UserRating;
use App\Domains\Ratings\Services\RatingAggregator;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Enums\SponsorPeriodStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use App\Models\User;

test('entity page exposes the public rating and the authenticated user rating', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create();
    UserRating::create([
        'user_id' => $user->id,
        'entity_id' => $entity->id,
        'rating' => 4,
    ]);
    app(RatingAggregator::class)->refresh($entity->id);

    $this->actingAs($user)
        ->get(route('entities.show', $entity->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entities/Show')
            ->where('rating.rating_count', 1)
            ->where('rating.rating_average', 4)
            ->where('rating.user_rating', 4)
        );
});

test('guest entity page does not expose another users rating selection', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create();
    UserRating::create([
        'user_id' => $user->id,
        'entity_id' => $entity->id,
        'rating' => 5,
    ]);
    app(RatingAggregator::class)->refresh($entity->id);

    $this->get(route('entities.show', $entity->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rating.rating_count', 1)
            ->where('rating.rating_average', 5)
            ->where('rating.user_rating', null)
        );
});

test('entity page exposes public reviews list and increments views_count for active sponsored entry', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso']);
    $entity = Entity::factory()->create();

    UserRating::create([
        'user_id' => $user->id,
        'entity_id' => $entity->id,
        'rating' => 5,
        'review' => 'Pelayanan sangat memuaskan dan cepat respon!',
    ]);
    app(RatingAggregator::class)->refresh($entity->id);

    $period = SponsorPeriod::factory()->create([
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'status' => SponsorPeriodStatus::Active,
    ]);

    $sponsoredEntry = SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 50000,
        'status' => SponsoredEntryStatus::Active,
        'views_count' => 10,
    ]);

    $this->get(route('entities.show', $entity->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('userReviews', 1)
            ->where('userReviews.0.review', 'Pelayanan sangat memuaskan dan cepat respon!')
            ->where('userReviews.0.user_name', 'Budi Santoso')
            ->where('leaderboard.is_active', true)
            ->where('leaderboard.views_count', 11)
        );

    expect($sponsoredEntry->fresh()->views_count)->toBe(11);
});
