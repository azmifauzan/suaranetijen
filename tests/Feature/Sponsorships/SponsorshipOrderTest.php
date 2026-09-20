<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sponsorships\Enums\SponsorshipOrderStatus;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Models\User;
use App\Notifications\SponsorGuestAccessNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

test('guest without an account can create a sponsorship order by email, no login required', function () {
    Notification::fake();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    Http::fake([
        'https://api-pay.sumopod.com/api/v1/payments' => Http::response([
            'payment_id' => 'pay_guest_123',
            'payment_link_url' => 'https://checkout.sumopod.com/pay/order-guest-123',
            'status' => 'pending',
        ], 200),
    ]);

    $response = $this->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $entity->id,
        'amount' => 50000,
        'email' => 'sponsor-guest@example.com',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.payment_link_url', 'https://checkout.sumopod.com/pay/order-guest-123');

    $user = User::query()->where('email', 'sponsor-guest@example.com')->first();
    expect($user)->not->toBeNull();

    $orderId = $response->json('data.id');
    $this->assertDatabaseHas('sponsorship_orders', [
        'id' => $orderId,
        'user_id' => $user->id,
        'amount' => 50000,
    ]);

    // The guest's session is now authenticated as the resolved user (survives the
    // Sumopod checkout redirect, so the post-payment status page works with no token).
    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($user, SponsorGuestAccessNotification::class);
});

test('guest checkout reuses an existing account by email instead of duplicating it', function () {
    Notification::fake();
    $existing = User::factory()->create(['email' => 'returning-sponsor@example.com']);
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    Http::fake([
        'https://api-pay.sumopod.com/api/v1/payments' => Http::response([
            'payment_id' => 'pay_returning_123',
            'payment_link_url' => 'https://checkout.sumopod.com/pay/order-returning-123',
            'status' => 'pending',
        ], 200),
    ]);

    $this->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $entity->id,
        'amount' => 50000,
        'email' => 'returning-sponsor@example.com',
    ])->assertCreated();

    $this->assertDatabaseCount('users', 1);
    $this->assertAuthenticatedAs($existing);
});

test('guest without an email is rejected', function () {
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    $this->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $entity->id,
        'amount' => 50000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('a banned guest cannot create a sponsorship order', function () {
    User::factory()->create(['email' => 'banned@example.com', 'is_banned' => true]);
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    $this->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $entity->id,
        'amount' => 50000,
        'email' => 'banned@example.com',
    ])->assertForbidden();

    $this->assertDatabaseCount('sponsorship_orders', 0);
});

test('sponsoring a URL with no existing entity match creates one, Disabled and invisible until payment', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    Http::fake([
        'https://api-pay.sumopod.com/api/v1/payments' => Http::response([
            'payment_id' => 'pay_newentity_1',
            'payment_link_url' => 'https://checkout.sumopod.com/pay/order-newentity-1',
            'status' => 'pending',
        ], 200),
    ]);

    $response = $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'new_entity_name' => 'Brand Baru Belum Terdaftar',
        'new_entity_category_id' => $category->id,
        'new_entity_url' => 'https://brand-baru.example/',
        'amount' => 10000,
    ]);

    $response->assertCreated();

    $entity = Entity::query()->where('name', 'Brand Baru Belum Terdaftar')->first();
    expect($entity)->not->toBeNull()
        ->and($entity->type)->toBe(EntityType::Brand)
        ->and($entity->category_id)->toBe($category->id)
        ->and($entity->status)->toBe(EntityStatus::Disabled)
        ->and($entity->searchable)->toBeFalse()
        ->and($entity->rankable)->toBeFalse();

    $this->assertDatabaseHas('entity_aliases', [
        'entity_id' => $entity->id,
        'alias' => 'Brand Baru Belum Terdaftar',
    ]);

    $this->assertDatabaseHas('sponsorship_orders', [
        'id' => $response->json('data.id'),
        'amount' => 10000,
        'status' => SponsorshipOrderStatus::Pending->value,
    ]);
});

test('new-entity sponsorship requires a name and a category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'new_entity_category_id' => $category->id,
        'new_entity_url' => 'https://brand-baru.example/',
        'amount' => 10000,
    ])->assertUnprocessable()->assertJsonValidationErrors(['new_entity_name']);

    $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'new_entity_name' => 'Brand Baru',
        'new_entity_url' => 'https://brand-baru.example/',
        'amount' => 10000,
    ])->assertUnprocessable()->assertJsonValidationErrors(['new_entity_category_id']);

    $this->assertDatabaseCount('sponsorship_orders', 0);
});

test('a duplicate entity name gets a unique slug instead of failing', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    Entity::factory()->create(['name' => 'Kopi Kenangan', 'slug' => 'kopi-kenangan']);

    Http::fake([
        'https://api-pay.sumopod.com/api/v1/payments' => Http::response([
            'payment_id' => 'pay_dup_1',
            'payment_link_url' => 'https://checkout.sumopod.com/pay/order-dup-1',
            'status' => 'pending',
        ], 200),
    ]);

    $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'new_entity_name' => 'Kopi Kenangan',
        'new_entity_category_id' => $category->id,
        'new_entity_url' => 'https://kopikenangan.example/',
        'amount' => 10000,
    ])->assertCreated();

    $this->assertDatabaseCount('entities', 2);
    $slugs = Entity::query()->where('name', 'Kopi Kenangan')->pluck('slug');
    expect($slugs->unique())->toHaveCount(2);
});

test('authenticated user can create order and receives payment url', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    Http::fake([
        'https://api-pay.sumopod.com/api/v1/payments' => Http::response([
            'payment_id' => 'pay_abc123',
            'payment_link_url' => 'https://checkout.sumopod.com/pay/order-test-123',
            'status' => 'pending',
        ], 200),
    ]);

    $response = $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $entity->id,
        'amount' => 50000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.amount', 50000)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.payment_link_url', 'https://checkout.sumopod.com/pay/order-test-123');

    $orderId = $response->json('data.id');
    $this->assertDatabaseHas('sponsorship_orders', [
        'id' => $orderId,
        'user_id' => $user->id,
        'amount' => 50000,
        'provider_order_id' => "SNT-SPN-{$orderId}",
        'provider_payment_id' => 'pay_abc123',
        'status' => SponsorshipOrderStatus::Pending->value,
    ]);
});

test('it rejects amounts below minimum configured threshold', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $entity->id,
        'amount' => 500, // < 1000 min
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('it rejects amounts that are not multiples of increment', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $entity->id,
        'amount' => 1550, // not multiple of 1000
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('it rejects inactive or non-searchable entities', function () {
    $user = User::factory()->create();
    $inactive = Entity::factory()->create(['status' => EntityStatus::Disabled, 'searchable' => true]);
    $nonSearchable = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => false]);

    $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $inactive->id,
        'amount' => 10000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_id']);

    $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $nonSearchable->id,
        'amount' => 10000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_id']);
});

test('it rejects sponsoring a person-type (public figure) entity', function () {
    $user = User::factory()->create();
    $person = Entity::factory()->create([
        'type' => EntityType::Person,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $person->id,
        'amount' => 10000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_id']);

    $this->assertDatabaseCount('sponsorship_orders', 0);
});

test('it rejects a redirect_url pointing at an external origin', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $entity->id,
        'amount' => 10000,
        'redirect_url' => 'https://evil.example.com/phish',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['redirect_url']);
});

test('it accepts a redirect_url on this app\'s own origin', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    Http::fake([
        'https://api-pay.sumopod.com/api/v1/payments' => Http::response([
            'payment_id' => 'pay_own_origin',
            'payment_link_url' => 'https://checkout.sumopod.com/pay/order-own-origin',
            'status' => 'pending',
        ], 200),
    ]);

    $this->actingAs($user)->postJson(route('api.sponsor.orders.store'), [
        'entity_id' => $entity->id,
        'amount' => 10000,
        'redirect_url' => url('/sponsor?ok=1'),
    ])->assertCreated();
});

test('order owner can view order status, other users are forbidden', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $order = SponsorshipOrder::factory()->create(['user_id' => $owner->id]);

    // Owner can view
    $this->actingAs($owner)
        ->getJson(route('api.sponsor.orders.show', $order))
        ->assertOk()
        ->assertJsonPath('data.id', $order->id);

    // Other user is forbidden
    $this->actingAs($other)
        ->getJson(route('api.sponsor.orders.show', $order))
        ->assertForbidden();

    // Admin can view
    $this->actingAs($admin)
        ->getJson(route('api.sponsor.orders.show', $order))
        ->assertOk()
        ->assertJsonPath('data.id', $order->id);
});
