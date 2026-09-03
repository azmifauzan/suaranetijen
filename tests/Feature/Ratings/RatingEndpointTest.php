<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Ratings\Models\UserRating;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Models\User;

test('authenticated user can replace a rating without creating a second contribution', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create();

    $this->actingAs($user)
        ->putJson(route('api.entities.rating.update', $entity), ['rating' => 4])
        ->assertOk()
        ->assertJsonPath('data.rating', 4)
        ->assertJsonPath('data.rating_count', 1)
        ->assertJsonPath('data.rating_average', 4);

    $this->actingAs($user)
        ->putJson(route('api.entities.rating.update', $entity), ['rating' => 2])
        ->assertOk()
        ->assertJsonPath('data.rating', 2)
        ->assertJsonPath('data.rating_count', 1)
        ->assertJsonPath('data.rating_average', 2);

    $this->assertDatabaseCount('user_ratings', 1);
    $this->assertDatabaseHas('user_ratings', [
        'user_id' => $user->id,
        'entity_id' => $entity->id,
        'rating' => 2,
    ]);
    $this->assertDatabaseHas('rating_snapshots', [
        'entity_id' => $entity->id,
        'rating_count' => 1,
        'rating_average' => 2,
    ]);
});

test('authenticated user can delete their rating and the snapshot is recalculated', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create();
    UserRating::create([
        'user_id' => $user->id,
        'entity_id' => $entity->id,
        'rating' => 5,
    ]);

    $response = $this->actingAs($user)
        ->deleteJson(route('api.entities.rating.destroy', $entity));

    $response->assertOk()
        ->assertJsonPath('data.rating', null)
        ->assertJsonPath('data.rating_count', 0)
        ->assertJsonPath('data.rating_average', null);

    $this->assertDatabaseMissing('user_ratings', [
        'user_id' => $user->id,
        'entity_id' => $entity->id,
    ]);
    $this->assertDatabaseHas('rating_snapshots', [
        'entity_id' => $entity->id,
        'rating_count' => 0,
        'rating_average' => null,
    ]);
});

test('user cannot delete another users rating', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $entity = Entity::factory()->create();
    UserRating::create([
        'user_id' => $owner->id,
        'entity_id' => $entity->id,
        'rating' => 4,
    ]);

    $response = $this->actingAs($otherUser)
        ->deleteJson(route('api.entities.rating.destroy', $entity));

    $response->assertOk()
        ->assertJsonPath('data.rating', null)
        ->assertJsonPath('data.rating_count', 1)
        ->assertJsonPath('data.rating_average', 4);

    $this->assertDatabaseHas('user_ratings', [
        'user_id' => $owner->id,
        'entity_id' => $entity->id,
        'rating' => 4,
    ]);
});

test('guest cannot submit a rating', function () {
    $entity = Entity::factory()->create();

    $this->putJson(route('api.entities.rating.update', $entity), ['rating' => 5])
        ->assertUnauthorized();

    $this->assertDatabaseCount('user_ratings', 0);
});

test('rating outside 1 to 5 returns validation error without persistence', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create();

    $this->actingAs($user)
        ->putJson(route('api.entities.rating.update', $entity), ['rating' => 6])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rating')
        ->assertJsonPath('errors.rating.0', 'The rating field must be between 1 and 5.');

    $this->assertDatabaseCount('user_ratings', 0);
});

test('disabled entity rejects a rating', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->disabled()->create();

    $this->actingAs($user)
        ->putJson(route('api.entities.rating.update', $entity), ['rating' => 5])
        ->assertNotFound();

    $this->assertDatabaseCount('user_ratings', 0);
});

test('banned user cannot submit a rating', function () {
    $user = User::factory()->banned()->create();
    $entity = Entity::factory()->create();

    $this->actingAs($user)
        ->putJson(route('api.entities.rating.update', $entity), ['rating' => 5])
        ->assertForbidden();

    $this->assertDatabaseCount('user_ratings', 0);
});

test('rating endpoint returns too many requests after the user reaches the burst limit', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create();

    foreach (range(1, 10) as $rating) {
        $this->actingAs($user)
            ->putJson(route('api.entities.rating.update', $entity), ['rating' => (($rating - 1) % 5) + 1])
            ->assertOk();
    }

    $this->actingAs($user)
        ->putJson(route('api.entities.rating.update', $entity), ['rating' => 5])
        ->assertTooManyRequests();
});

test('saving a rating does not alter sentiment snapshots', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create();
    SentimentSnapshot::create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear,
        'positive_count' => 60,
        'neutral_count' => 20,
        'negative_count' => 20,
        'opinion_count' => 100,
        'score' => 70,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $this->actingAs($user)
        ->putJson(route('api.entities.rating.update', $entity), ['rating' => 5])
        ->assertOk();

    $this->assertDatabaseHas('sentiment_snapshots', [
        'entity_id' => $entity->id,
        'positive_count' => 60,
        'neutral_count' => 20,
        'negative_count' => 20,
        'opinion_count' => 100,
        'score' => 70,
    ]);
});
