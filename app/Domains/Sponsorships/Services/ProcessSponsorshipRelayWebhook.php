<?php

namespace App\Domains\Sponsorships\Services;

use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Enums\SponsorshipOrderStatus;
use App\Domains\Sponsorships\Enums\SponsorshipRelayEventStatus;
use App\Domains\Sponsorships\Exceptions\SponsorshipRelayWebhookRejected;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Domains\Sponsorships\Models\SponsorshipRelayEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessSponsorshipRelayWebhook
{
    /**
     * Process relayed Sumopod webhook payload idempotently.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: string, message: string}
     */
    public function process(
        string $svixId,
        string $eventType,
        ?string $environment,
        array $payload,
        string $rawBody
    ): array {
        // createOrFirst (not firstOrCreate) so a concurrent redelivery of the same svix_id
        // can't race two inserts past the unique constraint and throw.
        /** @var SponsorshipRelayEvent $event */
        $event = SponsorshipRelayEvent::query()->createOrFirst(
            ['svix_id' => $svixId],
            [
                'event_type' => $eventType,
                'environment' => $environment,
                'payload' => $payload,
                'status' => SponsorshipRelayEventStatus::Received,
            ]
        );

        if ($event->isDelivered()) {
            return [
                'status' => 'duplicate',
                'message' => 'Event already processed',
            ];
        }

        if (! in_array($eventType, ['payment.completed', 'payment.failed', 'payment.expired'], true)) {
            $event->update([
                'status' => SponsorshipRelayEventStatus::Ignored,
                'processed_at' => CarbonImmutable::now(),
            ]);

            return [
                'status' => 'ignored',
                'message' => "Event type {$eventType} ignored",
            ];
        }

        $data = $payload['data'] ?? [];
        $orderId = $data['order_id'] ?? null;
        $paymentId = $data['payment_id'] ?? null;
        $relayedAmount = $data['amount'] ?? null;

        if (! is_string($orderId) || empty($orderId)) {
            $event->update([
                'status' => SponsorshipRelayEventStatus::Failed,
                'last_error' => 'Missing order_id in webhook payload',
                'processed_at' => CarbonImmutable::now(),
            ]);

            throw new SponsorshipRelayWebhookRejected('Missing order_id in webhook payload');
        }

        try {
            DB::transaction(function () use ($event, $orderId, $paymentId, $relayedAmount, $eventType, $svixId): void {
                /** @var SponsorshipOrder|null $order */
                $order = SponsorshipOrder::query()
                    ->where('provider_order_id', $orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $order) {
                    $event->update([
                        'status' => SponsorshipRelayEventStatus::Failed,
                        'last_error' => "Order not found for provider_order_id: {$orderId}",
                        'processed_at' => CarbonImmutable::now(),
                    ]);

                    throw new SponsorshipRelayWebhookRejected("Sponsorship order not found: {$orderId}");
                }

                // Correlation check: amount must match what was created on our side
                if (! is_int($relayedAmount) || $relayedAmount !== $order->amount) {
                    $error = "Amount mismatch: relayed={$relayedAmount}, expected={$order->amount}";
                    $event->update([
                        'status' => SponsorshipRelayEventStatus::Failed,
                        'last_error' => $error,
                        'processed_at' => CarbonImmutable::now(),
                    ]);

                    throw new SponsorshipRelayWebhookRejected($error);
                }

                // Correlation check: payment_id if recorded earlier must match
                if ($order->provider_payment_id && is_string($paymentId) && $order->provider_payment_id !== $paymentId) {
                    $error = "Payment ID mismatch: relayed={$paymentId}, expected={$order->provider_payment_id}";
                    $event->update([
                        'status' => SponsorshipRelayEventStatus::Failed,
                        'last_error' => $error,
                        'processed_at' => CarbonImmutable::now(),
                    ]);

                    throw new SponsorshipRelayWebhookRejected($error);
                }

                // If already settled, mark event delivered and return
                if (! $order->isPending()) {
                    $event->update([
                        'status' => SponsorshipRelayEventStatus::Delivered,
                        'processed_at' => CarbonImmutable::now(),
                        'last_error' => null,
                    ]);

                    return;
                }

                $auditData = [
                    'event_type' => $eventType,
                    'svix_id' => $svixId,
                    'processed_at' => CarbonImmutable::now()->toIso8601String(),
                ];

                if ($eventType === 'payment.failed') {
                    $order->update([
                        'status' => SponsorshipOrderStatus::Failed,
                        'audit_payload' => $auditData,
                    ]);
                } elseif ($eventType === 'payment.expired') {
                    $order->update([
                        'status' => SponsorshipOrderStatus::Expired,
                        'audit_payload' => $auditData,
                    ]);
                } elseif ($eventType === 'payment.completed') {
                    $order->update([
                        'status' => SponsorshipOrderStatus::Paid,
                        'paid_at' => CarbonImmutable::now(),
                        'provider_payment_id' => $paymentId ?? $order->provider_payment_id,
                        'audit_payload' => $auditData,
                    ]);

                    // Update projection in sponsored_entries
                    $entry = $order->sponsoredEntry()->lockForUpdate()->first();
                    if ($entry) {
                        $entry->settled_total_amount += $order->amount;
                        if (! $entry->first_settled_at) {
                            $entry->first_settled_at = CarbonImmutable::now();
                        }
                        if ($entry->status === SponsoredEntryStatus::Pending) {
                            $entry->status = SponsoredEntryStatus::Active;
                        }
                        $entry->save();
                    }
                }

                $event->update([
                    'status' => SponsorshipRelayEventStatus::Delivered,
                    'processed_at' => CarbonImmutable::now(),
                    'last_error' => null,
                ]);
            });

            return [
                'status' => 'success',
                'message' => 'Processed successfully',
            ];
        } catch (Throwable $e) {
            Log::error('ProcessSponsorshipRelayWebhook failed', [
                'svix_id' => $svixId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
