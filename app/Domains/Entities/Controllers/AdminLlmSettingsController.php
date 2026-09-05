<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Entities\Requests\UpdateLlmSettingsRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminLlmSettingsController extends Controller
{
    /**
     * Show the shared LLM settings form (base_url/model/key/etc. used by
     * every LLM-backed feature — see LlmClient).
     */
    public function edit(): Response
    {
        $setting = LlmSetting::query()->first();

        return Inertia::render('Admin/LlmSettings/Edit', [
            'setting' => $setting !== null ? [
                'base_url' => $setting->base_url,
                'model' => $setting->model,
                'has_api_key' => $setting->api_key !== null,
                'max_tokens' => $setting->max_tokens,
                'temperature' => $setting->temperature,
                'timeout_seconds' => $setting->timeout_seconds,
            ] : null,
        ]);
    }

    /**
     * Update the shared LLM settings. A blank api_key keeps the stored one —
     * the form never round-trips the real key back to the browser.
     */
    public function update(UpdateLlmSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $setting = LlmSetting::query()->first() ?? new LlmSetting;

        if (($validated['api_key'] ?? '') === '') {
            unset($validated['api_key']);
        }

        $setting->fill($validated);
        $setting->updated_by = $request->user()->id;
        $setting->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'LLM settings updated successfully.']);

        return redirect()->back();
    }
}
