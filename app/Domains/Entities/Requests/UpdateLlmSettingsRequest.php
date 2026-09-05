<?php

namespace App\Domains\Entities\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLlmSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'base_url' => ['nullable', 'string', 'max:255', 'url'],
            'model' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:128000'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:600'],
        ];
    }
}
