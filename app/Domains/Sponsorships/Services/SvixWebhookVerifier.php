<?php

namespace App\Domains\Sponsorships\Services;

class SvixWebhookVerifier
{
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * Verify Svix webhook signature against raw body and headers.
     *
     * @param  string  $rawBody  The raw HTTP request body
     * @param  array<string, string>  $headers  Header map (case-insensitive keys handled)
     * @param  string  $secret  The webhook secret (with or without 'whsec_' prefix)
     * @param  int  $tolerance  Timestamp tolerance in seconds (default 300s = 5m)
     */
    public function verify(string $rawBody, array $headers, string $secret, int $tolerance = self::DEFAULT_TOLERANCE_SECONDS): bool
    {
        if (empty($secret)) {
            return false;
        }

        $headers = array_change_key_case($headers, CASE_LOWER);

        $svixId = $headers['svix-id'] ?? $headers['x-webhook-id'] ?? null;
        $svixTimestamp = $headers['svix-timestamp'] ?? $headers['x-webhook-timestamp'] ?? null;
        $svixSignature = $headers['svix-signature'] ?? $headers['x-webhook-signature'] ?? null;

        if (! $svixId || ! $svixTimestamp || ! $svixSignature) {
            return false;
        }

        // Verify timestamp within tolerance
        $timestamp = (int) $svixTimestamp;
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expectedSignature = $this->calculateSignature($svixId, $timestamp, $rawBody, $secret);
        if ($expectedSignature === '') {
            return false;
        }

        // Header can contain multiple signatures separated by space e.g. "v1,sig1 v1,sig2"
        $signatures = preg_split('/\s+/', trim((string) $svixSignature));
        if ($signatures === false || empty($signatures)) {
            return false;
        }

        foreach ($signatures as $signature) {
            $parts = explode(',', $signature, 2);
            if (count($parts) === 2 && $parts[0] === 'v1') {
                if (hash_equals($expectedSignature, $parts[1])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calculate HMAC-SHA256 signature and return base64 encoded string.
     */
    public function calculateSignature(string $svixId, int $timestamp, string $rawBody, string $secret): string
    {
        $key = $this->decodeSecret($secret);
        if ($key === null) {
            return '';
        }

        $toSign = "{$svixId}.{$timestamp}.{$rawBody}";

        return base64_encode(hash_hmac('sha256', $toSign, $key, true));
    }

    /**
     * Generate standard Svix header value: "v1,<base64>"
     */
    public function generateSignatureHeader(string $svixId, int $timestamp, string $rawBody, string $secret): string
    {
        $signature = $this->calculateSignature($svixId, $timestamp, $rawBody, $secret);
        if ($signature === '') {
            throw new \InvalidArgumentException('Invalid Svix signing secret.');
        }

        return 'v1,'.$signature;
    }

    /**
     * Decode secret key. If secret starts with 'whsec_', decode base64 portion.
     */
    private function decodeSecret(string $secret): ?string
    {
        if (str_starts_with($secret, 'whsec_')) {
            $decoded = base64_decode(substr($secret, 6), true);

            return $decoded === false || $decoded === '' ? null : $decoded;
        }

        return $secret === '' ? null : $secret;
    }
}
