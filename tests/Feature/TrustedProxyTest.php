<?php

test('the app trusts X-Forwarded-Proto from any upstream reverse proxy', function () {
    $response = $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/up');

    $response->assertStatus(200);
    expect($response->baseRequest->isSecure())->toBeTrue();
});

test('the app does not treat plain HTTP requests as secure', function () {
    $response = $this->get('/up');

    expect($response->baseRequest->isSecure())->toBeFalse();
});
