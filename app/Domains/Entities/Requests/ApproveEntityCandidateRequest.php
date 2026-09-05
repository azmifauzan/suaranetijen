<?php

namespace App\Domains\Entities\Requests;

use App\Domains\Entities\Enums\EntityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveEntityCandidateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', Rule::enum(EntityType::class)],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'parent_id' => ['nullable', 'integer', 'exists:entities,id'],
            'aliases' => ['array'],
            'aliases.*' => ['string', 'max:255'],
        ];
    }
}
