<?php

test('the app trusts X-Forwarded-Proto from a private-network reverse proxy', function () {
    $response = $this
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->get('/up');

    $response->assertStatus(200);
    expect($response->baseRequest->isSecure())->toBeTrue();
});

test('the app does not trust X-Forwarded-Proto from outside the private network', function () {
    $response = $this
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->get('/up');

    expect($response->baseRequest->isSecure())->toBeFalse();
});
