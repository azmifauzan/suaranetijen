<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Enums\SponsorshipOrderStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Domains\Sponsorships\Services\ProcessSponsorshipRelayWebhook;
use App\Domains\Sponsorships\Services\SvixWebhookVerifier;
use App\Models\User;

beforeEach(function () {
    config()->set('sponsorship.sumopod.relay_secret', 'test_shared_secret_abc123');
});

function createSignedHeaders(string $body, string $svixId, ?int $timestamp = null, ?string $secret = null): array
{
    $secret ??= config('sponsorship.sumopod.relay_secret');
    $timestamp ??= time();
    $verifier = new SvixWebhookVerifier;
    $signature = $verifier->generateSignatureHeader($svixId, $timestamp, $body, $secret);

    return [
        'X-Webhook-Id' => $svixId,
        'X-Webhook-Timestamp' => (string) $timestamp,
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Environment' => 'live',
    ];
}

test('it rejects webhook when signature is missing or invalid', function () {
    $payload = ['event_type' => 'payment.completed', 'data' => []];
    $body = json_encode($payload);

    // Missing signature
    $this->postJson(route('api.sponsor.webhooks.relay'), $payload)
        ->assertUnauthorized();

    // Invalid signature
    $headers = [
        'X-Webhook-Id' => 'msg_123',
        'X-Webhook-Timestamp' => (string) time(),
        'X-Webhook-Signature' => 'v1,bad_sig',
    ];

    $this->postJson(route('api.sponsor.webhooks.relay'), $payload, $headers)
        ->assertUnauthorized();
});

test('it rejects webhook when timestamp exceeds tolerance', function () {
    $payload = ['event_type' => 'payment.completed', 'data' => []];
    $body = json_encode($payload);
    $headers = createSignedHeaders($body, 'msg_expired', time() - 400); // 400s > 300s

    $this->postJson(route('api.sponsor.webhooks.relay'), $payload, $headers)
        ->assertUnauthorized();
});

test('it successfully settles order and updates entry total on payment.completed', function () {
    $user = User::factory()->create();
    $period = SponsorPeriod::factory()->create();
    $entity = Entity::factory()->create();

    $entry = SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 0,
        'first_settled_at' => null,
        'status' => SponsoredEntryStatus::Pending,
    ]);

    $order = SponsorshipOrder::factory()->create([
        'sponsored_entry_id' => $entry->id,
        'user_id' => $user->id,
        'amount' => 50000,
        'provider_order_id' => 'SNT-SPN-101',
        'provider_payment_id' => 'pay_101',
        'status' => SponsorshipOrderStatus::Pending,
    ]);

    $payload = [
        'event_type' => 'payment.completed',
        'data' => [
            'order_id' => 'SNT-SPN-101',
            'payment_id' => 'pay_101',
            'amount' => 50000,
            'currency' => 'IDR',
        ],
    ];

    $body = json_encode($payload);
    $headers = createSignedHeaders($body, 'svix_event_001');

    $response = $this->postJson(route('api.sponsor.webhooks.relay'), $payload, $headers);

    $response->assertOk()
        ->assertJsonPath('success', true);

    $order->refresh();
    expect($order->status)->toBe(SponsorshipOrderStatus::Paid);
    expect($order->paid_at)->not->toBeNull();

    $entry->refresh();
    expect($entry->settled_total_amount)->toBe(50000);
    expect($entry->status)->toBe(SponsoredEntryStatus::Active);
    expect($entry->first_settled_at)->not->toBeNull();

    $this->assertDatabaseHas('sponsorship_relay_events', [
        'svix_id' => 'svix_event_001',
        'status' => 'delivered',
    ]);
});

test('community model: multiple sponsors accumulate on the same entity', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $period = SponsorPeriod::factory()->create();
    $entity = Entity::factory()->create();

    $entry = SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 0,
        'status' => SponsoredEntryStatus::Pending,
    ]);

    $order1 = SponsorshipOrder::factory()->create([
        'sponsored_entry_id' => $entry->id,
        'user_id' => $user1->id,
        'amount' => 50000,
        'provider_order_id' => 'SNT-SPN-1001',
        'status' => SponsorshipOrderStatus::Pending,
    ]);

    $order2 = SponsorshipOrder::factory()->create([
        'sponsored_entry_id' => $entry->id,
        'user_id' => $user2->id,
        'amount' => 100000,
        'provider_order_id' => 'SNT-SPN-1002',
        'status' => SponsorshipOrderStatus::Pending,
    ]);

    // Webhook 1
    $payload1 = [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => 'SNT-SPN-1001', 'payment_id' => 'pay_1', 'amount' => 50000],
    ];
    $this->postJson(route('api.sponsor.webhooks.relay'), $payload1, createSignedHeaders(json_encode($payload1), 'svix_1'))
        ->assertOk();

    // Webhook 2
    $payload2 = [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => 'SNT-SPN-1002', 'payment_id' => 'pay_2', 'amount' => 100000],
    ];
    $this->postJson(route('api.sponsor.webhooks.relay'), $payload2, createSignedHeaders(json_encode($payload2), 'svix_2'))
        ->assertOk();

    $entry->refresh();
    // 50k + 100k = 150k
    expect($entry->settled_total_amount)->toBe(150000);
});

test('duplicate relay webhook delivery is idempotent and does not double credit', function () {
    $period = SponsorPeriod::factory()->create();
    $entity = Entity::factory()->create();
    $entry = SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 0,
    ]);
    $order = SponsorshipOrder::factory()->create([
        'sponsored_entry_id' => $entry->id,
        'amount' => 50000,
        'provider_order_id' => 'SNT-SPN-999',
        'status' => SponsorshipOrderStatus::Pending,
    ]);

    $payload = [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => 'SNT-SPN-999', 'payment_id' => 'pay_999', 'amount' => 50000],
    ];
    $body = json_encode($payload);
    $headers = createSignedHeaders($body, 'svix_dup_test');

    // First delivery
    $this->postJson(route('api.sponsor.webhooks.relay'), $payload, $headers)->assertOk();
    $entry->refresh();
    expect($entry->settled_total_amount)->toBe(50000);

    // Second delivery with identical svix_id
    $this->postJson(route('api.sponsor.webhooks.relay'), $payload, $headers)
        ->assertOk()
        ->assertJsonPath('result.status', 'duplicate');

    $entry->refresh();
    // Still 50k, never 100k
    expect($entry->settled_total_amount)->toBe(50000);
});

test('it rejects and fails when payload amount does not match recorded order amount', function () {
    $period = SponsorPeriod::factory()->create();
    $entity = Entity::factory()->create();
    $entry = SponsoredEntry::factory()->create(['period_id' => $period->id, 'entity_id' => $entity->id, 'settled_total_amount' => 0]);
    $order = SponsorshipOrder::factory()->create([
        'sponsored_entry_id' => $entry->id,
        'amount' => 50000,
        'provider_order_id' => 'SNT-SPN-MISMATCH',
        'status' => SponsorshipOrderStatus::Pending,
    ]);

    $payload = [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => 'SNT-SPN-MISMATCH', 'payment_id' => 'pay_1', 'amount' => 10000], // Mismatch 10k vs 50k
    ];
    $headers = createSignedHeaders(json_encode($payload), 'svix_mismatch');

    $this->postJson(route('api.sponsor.webhooks.relay'), $payload, $headers)
        ->assertUnprocessable();

    $entry->refresh();
    expect($entry->settled_total_amount)->toBe(0);
});

test('it handles payment.failed and payment.expired without incrementing settled amount', function () {
    $period = SponsorPeriod::factory()->create();
    $entity = Entity::factory()->create();
    $entry = SponsoredEntry::factory()->create(['period_id' => $period->id, 'entity_id' => $entity->id, 'settled_total_amount' => 0]);
    $order = SponsorshipOrder::factory()->create([
        'sponsored_entry_id' => $entry->id,
        'amount' => 50000,
        'provider_order_id' => 'SNT-SPN-FAIL',
        'status' => SponsorshipOrderStatus::Pending,
    ]);

    $payload = [
        'event_type' => 'payment.failed',
        'data' => ['order_id' => 'SNT-SPN-FAIL', 'payment_id' => 'pay_1', 'amount' => 50000],
    ];
    $headers = createSignedHeaders(json_encode($payload), 'svix_failed');

    $this->postJson(route('api.sponsor.webhooks.relay'), $payload, $headers)->assertOk();

    $order->refresh();
    expect($order->status)->toBe(SponsorshipOrderStatus::Failed);
    $entry->refresh();
    expect($entry->settled_total_amount)->toBe(0);
});

test('it returns 422 (non-retryable) for a permanent correlation failure but 500 (retryable) for an unexpected error', function () {
    // Permanent: order genuinely doesn't exist. satsetui must not retry this.
    $payloadUnroutable = [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => 'SNT-SPN-DOES-NOT-EXIST', 'payment_id' => 'pay_x', 'amount' => 10000],
    ];
    $this->postJson(
        route('api.sponsor.webhooks.relay'),
        $payloadUnroutable,
        createSignedHeaders(json_encode($payloadUnroutable), 'svix_unroutable')
    )->assertStatus(422);

    // Transient: processor throws something other than our own rejection exception.
    // satsetui's retry-with-backoff only fires on a 5xx (or 429), so this must not be a 4xx.
    $this->app->bind(ProcessSponsorshipRelayWebhook::class, fn () => new class extends ProcessSponsorshipRelayWebhook
    {
        public function process(string $svixId, string $eventType, ?string $environment, array $payload, string $rawBody): array
        {
            throw new RuntimeException('simulated transient failure (e.g. DB connection drop)');
        }
    });

    $payloadTransient = [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => 'SNT-SPN-TRANSIENT', 'payment_id' => 'pay_y', 'amount' => 10000],
    ];
    $this->postJson(
        route('api.sponsor.webhooks.relay'),
        $payloadTransient,
        createSignedHeaders(json_encode($payloadTransient), 'svix_transient')
    )->assertStatus(500);
});

test('it acknowledges and ignores non-payment events', function () {
    $payload = [
        'event_type' => 'payment.test',
        'data' => [],
    ];
    $headers = createSignedHeaders(json_encode($payload), 'svix_test_event');

    $this->postJson(route('api.sponsor.webhooks.relay'), $payload, $headers)
        ->assertOk()
        ->assertJsonPath('result.status', 'ignored');
});
