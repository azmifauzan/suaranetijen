<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Ratings\Models\UserRating;
use App\Domains\Ratings\Services\RatingAggregator;
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
