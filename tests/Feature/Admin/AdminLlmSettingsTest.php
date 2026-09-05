<?php

use App\Domains\Entities\Models\LlmSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view and update the shared LLM settings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/llm-settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/LlmSettings/Edit'));

    $this->actingAs($admin)
        ->put('/admin/llm-settings', [
            'base_url' => 'https://llm.internal/v1',
            'model' => 'gpt-4o-mini',
            'api_key' => 'super-secret',
            'max_tokens' => 2048,
            'temperature' => 0.3,
            'timeout_seconds' => 45,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'LLM settings updated successfully.']);

    $setting = LlmSetting::query()->first();
    expect($setting->base_url)->toBe('https://llm.internal/v1')
        ->and($setting->model)->toBe('gpt-4o-mini')
        ->and($setting->api_key)->toBe('super-secret')
        ->and($setting->max_tokens)->toBe(2048)
        ->and($setting->updated_by)->toBe($admin->id);
});

test('a blank api_key on update keeps the existing stored key', function () {
    $admin = User::factory()->admin()->create();
    LlmSetting::create(['api_key' => 'keep-me', 'model' => 'old-model']);

    $this->actingAs($admin)
        ->put('/admin/llm-settings', [
            'base_url' => null,
            'model' => 'new-model',
            'api_key' => '',
            'max_tokens' => 1024,
            'temperature' => 0.2,
            'timeout_seconds' => 30,
        ])
        ->assertRedirect();

    expect(LlmSetting::query()->first()->api_key)->toBe('keep-me');
});

test('non-admin cannot access LLM settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/llm-settings')->assertForbidden();
});
