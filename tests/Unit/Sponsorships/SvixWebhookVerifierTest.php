<?php

use App\Domains\Sponsorships\Services\SvixWebhookVerifier;

test('it verifies a valid signature header', function () {
    $verifier = new SvixWebhookVerifier;
    $secret = 'test_secret_key_12345';
    $svixId = 'msg_test_012345';
    $timestamp = time();
    $body = json_encode(['event_type' => 'payment.completed', 'data' => ['order_id' => 'SNT-SPN-1']]);

    $signature = $verifier->generateSignatureHeader($svixId, $timestamp, $body, $secret);

    $headers = [
        'X-Webhook-Id' => $svixId,
        'X-Webhook-Timestamp' => (string) $timestamp,
        'X-Webhook-Signature' => $signature,
    ];

    expect($verifier->verify($body, $headers, $secret))->toBeTrue();
});

test('it verifies signature with whsec prefix', function () {
    $verifier = new SvixWebhookVerifier;
    // whsec_ followed by base64 string
    $rawSecret = 'raw_super_secret_bytes_1234567890';
    $secret = 'whsec_'.base64_encode($rawSecret);
    $svixId = 'msg_test_098765';
    $timestamp = time();
    $body = '{"test":"payload"}';

    $signature = $verifier->generateSignatureHeader($svixId, $timestamp, $body, $secret);

    $headers = [
        'svix-id' => $svixId,
        'svix-timestamp' => (string) $timestamp,
        'svix-signature' => $signature,
    ];

    expect($verifier->verify($body, $headers, $secret))->toBeTrue();
});

test('it rejects an invalid signature', function () {
    $verifier = new SvixWebhookVerifier;
    $secret = 'test_secret';
    $svixId = 'msg_test';
    $timestamp = time();
    $body = '{"foo":"bar"}';

    $headers = [
        'X-Webhook-Id' => $svixId,
        'X-Webhook-Timestamp' => (string) $timestamp,
        'X-Webhook-Signature' => 'v1,invalid_signature_hash',
    ];

    expect($verifier->verify($body, $headers, $secret))->toBeFalse();
});

test('it rejects timestamps outside the tolerance window', function () {
    $verifier = new SvixWebhookVerifier;
    $secret = 'test_secret';
    $svixId = 'msg_test';
    // 10 minutes ago (> 300s)
    $timestamp = time() - 600;
    $body = '{"foo":"bar"}';

    $signature = $verifier->generateSignatureHeader($svixId, $timestamp, $body, $secret);

    $headers = [
        'X-Webhook-Id' => $svixId,
        'X-Webhook-Timestamp' => (string) $timestamp,
        'X-Webhook-Signature' => $signature,
    ];

    expect($verifier->verify($body, $headers, $secret, 300))->toBeFalse();
});

test('it returns false when secret is empty', function () {
    $verifier = new SvixWebhookVerifier;
    $headers = [
        'X-Webhook-Id' => 'id',
        'X-Webhook-Timestamp' => (string) time(),
        'X-Webhook-Signature' => 'v1,sig',
    ];

    expect($verifier->verify('{}', $headers, ''))->toBeFalse();
});
