<?php

namespace App\Domains\Sponsorships\Controllers\Api;

use App\Domains\Sponsorships\Exceptions\SponsorshipRelayWebhookRejected;
use App\Domains\Sponsorships\Services\ProcessSponsorshipRelayWebhook;
use App\Domains\Sponsorships\Services\SvixWebhookVerifier;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SumopodRelayWebhookController extends Controller
{
    public function handle(
        Request $request,
        SvixWebhookVerifier $verifier,
        ProcessSponsorshipRelayWebhook $processor
    ): JsonResponse {
        $rawBody = (string) $request->getContent();

        // Same bound as satsetui's own ingress — a relay body has no legitimate reason to be
        // this large, and rejecting early avoids parsing/verifying an oversized payload.
        if (strlen($rawBody) > 65536) {
            return response()->json(['error' => 'Payload terlalu besar.'], 413);
        }

        $secret = (string) config('sponsorship.sumopod.relay_secret', '');
        $tolerance = (int) config('sponsorship.sumopod.relay_tolerance', 300);

        $headers = [
            'svix-id' => $request->header('X-Webhook-Id'),
            'svix-timestamp' => $request->header('X-Webhook-Timestamp'),
            'svix-signature' => $request->header('X-Webhook-Signature'),
        ];

        if (empty($secret) || ! $verifier->verify($rawBody, $headers, $secret, $tolerance)) {
            Log::warning('Sumopod relay webhook rejected: invalid signature or timestamp', [
                'headers' => $headers,
            ]);

            return response()->json(['error' => 'Tanda tangan webhook relay tidak valid atau kadaluarsa.'], 401);
        }

        // Parse the exact bytes the signature covered, not Request::all() — the latter merges
        // query-string parameters and is subject to Laravel's own JSON/form parsing rules,
        // neither of which is guaranteed to match what was signed.
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'Payload JSON tidak valid.'], 400);
        }

        $svixId = (string) $request->header('X-Webhook-Id');
        $environment = (string) $request->header('X-Webhook-Environment');
        $eventType = (string) ($payload['event_type'] ?? '');

        try {
            $result = $processor->process($svixId, $eventType, $environment, $payload, $rawBody);

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (SponsorshipRelayWebhookRejected $e) {
            // Permanently unprocessable (unroutable order, correlation mismatch) — already
            // recorded on the sponsorship_relay_events row. satsetui treats 4xx (not 429) as
            // non-retryable, which is correct here: redelivering won't change the outcome.
            Log::error('Sumopod relay webhook rejected', [
                'svix_id' => $svixId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            // Anything else (DB connection drop, deadlock, ...) is transient — a 5xx here is
            // what makes satsetui's retry-with-backoff actually retry it instead of giving up.
            Log::error('Sumopod relay webhook processing exception', [
                'svix_id' => $svixId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Gagal memproses webhook relay.'], 500);
        }
    }
}
