<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Models\EntityCandidate;
use App\Domains\Entities\Models\EntitySubmission;
use App\Domains\Sponsorships\Enums\SponsorshipOrderStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
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
    $this->post('/usulan', submissionPayload())->assertRedirect('/login');
});

test('banned user is forbidden', function () {
    $user = User::factory()->create(['is_banned' => true]);

    $this->actingAs($user)->post('/usulan', submissionPayload())->assertForbidden();
    expect(EntitySubmission::count())->toBe(0);
});

test('submission joins the review queue as a pending user_submission candidate', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/usulan', submissionPayload(['is_owner' => '1', 'website_url' => 'https://kopisenja.id']))
        ->assertRedirect('/usulan');

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

    $this->actingAs($a)->post('/usulan', $payload);
    $this->actingAs($a)->post('/usulan', $payload);
    $this->actingAs($b)->post('/usulan', array_merge($payload, ['name' => 'KOPI  senja']));

    expect(EntityCandidate::count())->toBe(1)
        ->and(EntityCandidate::first()->frequency_score)->toBe(2)
        ->and(EntitySubmission::count())->toBe(2);
});

test('a name that already exists is rejected with a link to the entity', function () {
    $entity = Entity::factory()->create(['name' => 'Kopi Senja', 'slug' => 'kopi-senja']);
    EntityAlias::factory()->create(['entity_id' => $entity->id, 'alias' => 'Kopi Senja', 'normalized_alias' => 'kopi senja']);

    $this->actingAs(User::factory()->create())
        ->post('/usulan', submissionPayload())
        ->assertSessionHasErrors('name');

    expect(EntityCandidate::count())->toBe(0);
});

test('public figure categories and person type are refused', function () {
    $tokoh = Category::factory()->create(['slug' => 'tokoh-publik']);
    $politisi = Category::factory()->create(['slug' => 'politisi', 'parent_id' => $tokoh->id]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/usulan', submissionPayload(['category_id' => $politisi->id]))
        ->assertSessionHasErrors('category_id');
    $this->actingAs($user)->post('/usulan', submissionPayload(['entity_type' => 'person']))
        ->assertSessionHasErrors('entity_type');
});

test('brand type ignores a parent entity', function () {
    $parent = Entity::factory()->create(['type' => 'brand']);

    $this->actingAs(User::factory()->create())
        ->post('/usulan', submissionPayload(['parent_entity_id' => $parent->id]));

    expect(EntityCandidate::first()->suggested_parent_entity_id)->toBeNull();
});

test('sixth submission in a day is throttled', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->actingAs($user)->post('/usulan', submissionPayload(['name' => "Merek Uji {$i}"]))->assertRedirect();
    }

    $this->actingAs($user)->post('/usulan', submissionPayload(['name' => 'Merek Uji 6']))->assertStatus(429);
});

test('index lists only the own submissions with candidate status', function () {
    $user = User::factory()->create();
    $candidate = EntityCandidate::factory()->rejected()->create();
    EntitySubmission::factory()->create(['user_id' => $user->id, 'entity_candidate_id' => $candidate->id, 'name' => 'Punya Saya']);
    EntitySubmission::factory()->create(['name' => 'Punya Orang']);

    $this->actingAs($user)->get('/usulan')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EntitySubmissions/Index')
            ->has('submissions', 1)
            ->where('submissions.0.name', 'Punya Saya')
            ->where('submissions.0.status', 'rejected'));
});

test('index merges own sponsor orders, newest first, paid ones read approved and unpaid are not linked', function () {
    $user = User::factory()->create();
    $entry = SponsoredEntry::factory()->create();
    $pendingEntry = SponsoredEntry::factory()->create();
    EntitySubmission::factory()->create(['user_id' => $user->id, 'name' => 'Usulan Lama', 'created_at' => now()->subDay()]);
    SponsorshipOrder::factory()->paid()->create(['user_id' => $user->id, 'sponsored_entry_id' => $entry->id, 'amount' => 25000, 'created_at' => now()->subHour()]);
    SponsorshipOrder::factory()->create([
        'user_id' => $user->id,
        'sponsored_entry_id' => $pendingEntry->id,
        'status' => SponsorshipOrderStatus::Pending,
        'payment_link_url' => 'https://checkout.example/pay/1',
        'expires_at' => now()->addHour(),
    ]);
    SponsorshipOrder::factory()->create(['sponsored_entry_id' => $entry->id]);

    $this->actingAs($user)->get('/usulan')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions', 3)
            ->where('submissions.0.key', 'sponsor-entity-'.$pendingEntry->entity_id)
            ->where('submissions.0.status_label', 'Menunggu Pembayaran')
            ->where('submissions.0.payment_link_url', 'https://checkout.example/pay/1')
            ->where('submissions.0.entity_slug', null)
            ->where('submissions.1.key', 'sponsor-entity-'.$entry->entity_id)
            ->where('submissions.1.kind', 'sponsor')
            ->where('submissions.1.status_label', 'Disetujui')
            ->where('submissions.1.entity_slug', $entry->entity->slug)
            ->where('submissions.1.amount', 25000)
            ->where('submissions.2.kind', 'submission'));
});

test('several orders for one brand collapse into a single row with the paid total', function () {
    $user = User::factory()->create();
    $entry = SponsoredEntry::factory()->create();
    $other = SponsoredEntry::factory()->create();
    SponsorshipOrder::factory()->paid()->count(3)->create(['user_id' => $user->id, 'sponsored_entry_id' => $entry->id, 'amount' => 1000]);
    SponsorshipOrder::factory()->create(['user_id' => $user->id, 'sponsored_entry_id' => $entry->id, 'status' => SponsorshipOrderStatus::Failed, 'amount' => 9999]);
    SponsorshipOrder::factory()->paid()->create(['user_id' => $user->id, 'sponsored_entry_id' => $other->id, 'amount' => 5000]);

    $this->actingAs($user)->get('/usulan')
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions', 2)
            ->where('submissions', fn ($rows) => collect($rows)->firstWhere('name', $entry->entity->name)['amount'] === 3000
                && collect($rows)->firstWhere('name', $entry->entity->name)['contributions'] === 3
                && collect($rows)->firstWhere('name', $entry->entity->name)['status_label'] === 'Disetujui'));
});

test('a paid brand with a newer unpaid top-up stays approved and offers the pay link', function () {
    $user = User::factory()->create();
    $entry = SponsoredEntry::factory()->create();
    SponsorshipOrder::factory()->paid()->create(['user_id' => $user->id, 'sponsored_entry_id' => $entry->id, 'amount' => 2000, 'created_at' => now()->subDay()]);
    SponsorshipOrder::factory()->create([
        'user_id' => $user->id,
        'sponsored_entry_id' => $entry->id,
        'status' => SponsorshipOrderStatus::Pending,
        'payment_link_url' => 'https://checkout.example/pay/topup',
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($user)->get('/usulan')
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions', 1)
            ->where('submissions.0.status_label', 'Disetujui')
            ->where('submissions.0.amount', 2000)
            ->where('submissions.0.payment_link_url', 'https://checkout.example/pay/topup'));
});

test('an expired sponsor order offers no payment link', function () {
    $user = User::factory()->create();
    SponsorshipOrder::factory()->create([
        'user_id' => $user->id,
        'status' => SponsorshipOrderStatus::Pending,
        'expires_at' => now()->subHour(),
    ]);

    $this->actingAs($user)->get('/usulan')
        ->assertInertia(fn (Assert $page) => $page->where('submissions.0.payment_link_url', null));
});

test('admin rejection note reaches the submitter', function () {
    $user = User::factory()->create();
    $candidate = EntityCandidate::factory()->create();
    EntitySubmission::factory()->create(['user_id' => $user->id, 'entity_candidate_id' => $candidate->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->post("/admin/entity-candidates/{$candidate->id}/reject", ['note' => 'Bukan brand yang dikenal publik'])
        ->assertRedirect();

    $this->actingAs($user)->get('/usulan')
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.status', 'rejected')
            ->where('submissions.0.review_note', 'Bukan brand yang dikenal publik'));
});

test('a name held by a non-public entity does not leak that entity', function () {
    $entity = Entity::factory()->create(['name' => 'Belum Tayang', 'slug' => 'belum-tayang', 'status' => 'disabled']);
    EntityAlias::factory()->create(['entity_id' => $entity->id, 'alias' => 'Belum Tayang', 'normalized_alias' => 'belum tayang']);

    $response = $this->actingAs(User::factory()->create())
        ->post('/usulan', submissionPayload(['name' => 'Belum Tayang']))
        ->assertSessionHasErrors('name');

    expect(session('errors')->first('name'))->not->toContain('belum-tayang');
});

test('user description stays off the candidate reasoning', function () {
    $this->actingAs(User::factory()->create())
        ->post('/usulan', submissionPayload(['description' => 'Kopi terbaik se-Indonesia']));

    expect(EntityCandidate::first()->reasoning)->toBeNull()
        ->and(EntitySubmission::first()->description)->toBe('Kopi terbaik se-Indonesia');
});

test('the sponsor path skips the review queue and hands off to the sponsor checkout with the data', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/usulan', submissionPayload([
            'sponsor' => '1',
            'name' => 'Kopi Senja',
            'entity_type' => 'product',
            'category_id' => $category->id,
            'website_url' => 'https://kopisenja.id',
        ]))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('leaderboard.index', [
            'url' => 'https://kopisenja.id',
            'name' => 'Kopi Senja',
            'category_id' => $category->id,
            'type' => 'product',
        ]));

    expect(EntityCandidate::count())->toBe(0)
        ->and(EntitySubmission::count())->toBe(0);
});

test('the sponsor path requires a website', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/usulan', submissionPayload(['sponsor' => '1']))
        ->assertSessionHasErrors(['website_url' => 'Isi website resmi untuk jalur sponsor.']);
});

test('the sponsor path still refuses a name that already exists', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create(['name' => 'Kopi Senja']);
    EntityAlias::factory()->create(['entity_id' => $entity->id, 'alias' => 'Kopi Senja', 'normalized_alias' => 'kopi senja']);

    $this->actingAs($user)->post('/usulan', submissionPayload(['sponsor' => '1', 'website_url' => 'https://kopisenja.id']))
        ->assertSessionHasErrors('name');
});
