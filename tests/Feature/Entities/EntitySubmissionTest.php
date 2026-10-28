<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Models\EntityCandidate;
use App\Domains\Entities\Models\EntitySubmission;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function submissionPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Kopi Senja',
        'entity_type' => 'brand',
        'category_id' => Category::factory()->create()->id,
    ], $overrides);
}

test('guest is redirected to login', function () {
    $this->post('/settings/usulan', submissionPayload())->assertRedirect('/login');
});

test('banned user is forbidden', function () {
    $user = User::factory()->create(['is_banned' => true]);

    $this->actingAs($user)->post('/settings/usulan', submissionPayload())->assertForbidden();
    expect(EntitySubmission::count())->toBe(0);
});

test('submission joins the review queue as a pending user_submission candidate', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/usulan', submissionPayload(['is_owner' => '1', 'website_url' => 'https://kopisenja.id']))
        ->assertRedirect('/settings/usulan');

    $candidate = EntityCandidate::where('normalized_term', 'kopi senja')->firstOrFail();
    expect($candidate->status)->toBe('pending')
        ->and($candidate->source_types)->toBe(['user_submission'])
        ->and($candidate->frequency_score)->toBe(1)
        ->and($candidate->suggested_name)->toBe('Kopi Senja');
    expect(EntitySubmission::where('user_id', $user->id)->firstOrFail()->is_owner)->toBeTrue();
});

test('two users on the same name share one candidate and the same user counts once', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $payload = submissionPayload();

    $this->actingAs($a)->post('/settings/usulan', $payload);
    $this->actingAs($a)->post('/settings/usulan', $payload);
    $this->actingAs($b)->post('/settings/usulan', array_merge($payload, ['name' => 'KOPI  senja']));

    expect(EntityCandidate::count())->toBe(1)
        ->and(EntityCandidate::first()->frequency_score)->toBe(2)
        ->and(EntitySubmission::count())->toBe(2);
});

test('a name that already exists is rejected with a link to the entity', function () {
    $entity = Entity::factory()->create(['name' => 'Kopi Senja', 'slug' => 'kopi-senja']);
    EntityAlias::factory()->create(['entity_id' => $entity->id, 'alias' => 'Kopi Senja', 'normalized_alias' => 'kopi senja']);

    $this->actingAs(User::factory()->create())
        ->post('/settings/usulan', submissionPayload())
        ->assertSessionHasErrors('name');

    expect(EntityCandidate::count())->toBe(0);
});

test('public figure categories and person type are refused', function () {
    $tokoh = Category::factory()->create(['slug' => 'tokoh-publik']);
    $politisi = Category::factory()->create(['slug' => 'politisi', 'parent_id' => $tokoh->id]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/usulan', submissionPayload(['category_id' => $politisi->id]))
        ->assertSessionHasErrors('category_id');
    $this->actingAs($user)->post('/settings/usulan', submissionPayload(['entity_type' => 'person']))
        ->assertSessionHasErrors('entity_type');
});

test('brand type ignores a parent entity', function () {
    $parent = Entity::factory()->create(['type' => 'brand']);

    $this->actingAs(User::factory()->create())
        ->post('/settings/usulan', submissionPayload(['parent_entity_id' => $parent->id]));

    expect(EntityCandidate::first()->suggested_parent_entity_id)->toBeNull();
});

test('sixth submission in a day is throttled', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->actingAs($user)->post('/settings/usulan', submissionPayload(['name' => "Merek Uji {$i}"]))->assertRedirect();
    }

    $this->actingAs($user)->post('/settings/usulan', submissionPayload(['name' => 'Merek Uji 6']))->assertStatus(429);
});

test('index lists only the own submissions with candidate status', function () {
    $user = User::factory()->create();
    $candidate = EntityCandidate::factory()->rejected()->create();
    EntitySubmission::factory()->create(['user_id' => $user->id, 'entity_candidate_id' => $candidate->id, 'name' => 'Punya Saya']);
    EntitySubmission::factory()->create(['name' => 'Punya Orang']);

    $this->actingAs($user)->get('/settings/usulan')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/EntitySubmissions')
            ->has('submissions', 1)
            ->where('submissions.0.name', 'Punya Saya')
            ->where('submissions.0.status', 'rejected'));
});

test('admin rejection note reaches the submitter', function () {
    $user = User::factory()->create();
    $candidate = EntityCandidate::factory()->create();
    EntitySubmission::factory()->create(['user_id' => $user->id, 'entity_candidate_id' => $candidate->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->post("/admin/entity-candidates/{$candidate->id}/reject", ['note' => 'Bukan brand yang dikenal publik'])
        ->assertRedirect();

    $this->actingAs($user)->get('/settings/usulan')
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.status', 'rejected')
            ->where('submissions.0.review_note', 'Bukan brand yang dikenal publik'));
});
